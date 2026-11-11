<?php

namespace App\Http\Controllers;

use App\Models\SupplierResource;
use App\Services\FlowtrackPurchaseOrderDelivery;
use App\Services\SupplierDashboardData;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Supplier-scoped PO workspace. The Main ERP owns all PO revisions and documents. */
final class SupplierPurchaseOrderController extends Controller
{
    public function index(Request $request, SupplierDashboardData $dashboardData)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'po_filter' => ['nullable', 'in:all,pending,generated'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $poFilter = (string) ($validated['po_filter'] ?? 'all');
        $query = SupplierResource::query()
            ->where('supplier_company_id', $request->user()->supplier_company_id)
            ->where('type', 'order')
            ->where('status', '!=', 'revoked');

        if ($search !== '') {
            // Reuse the existing supplier-scoped order-search projection. A search
            // never causes extra queries or accesses the buyer's orders database.
            $needle = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
            $query->where(static function ($q) use ($needle): void {
                $q->where('external_reference', 'like', $needle)
                    ->orWhere('payload->listing->search_text', 'like', $needle)
                    ->orWhere('payload->title', 'like', $needle);
            });
        }
        if ($poFilter === 'generated') {
            $query->whereJsonLength('payload->purchase_orders', '>', 0);
        } elseif ($poFilter === 'pending') {
            $query->where(static function ($q): void {
                $q->whereJsonLength('payload->purchase_orders', '=', 0)
                    ->orWhereNull('payload->purchase_orders');
            });
        }
        $orders = $query->orderByDesc('id')
            ->paginate(15, ['id', 'external_reference', 'status', 'payload'])
            ->withQueryString();

        return view('portal.purchase-orders.index', $this->shared($request, $dashboardData) + compact(
            'orders', 'search', 'poFilter'
        ));
    }

    public function show(Request $request, int $id, SupplierScope $scope, SupplierDashboardData $dashboardData)
    {
        $order = $this->order($request, $id, $scope);
        $payload = $order->payload ?? [];
        $pos = array_values($payload['purchase_orders'] ?? []);
        $selectedId = $request->integer('po');
        $selected = collect($pos)->first(static fn ($po) => (int) ($po['id'] ?? 0) === $selectedId) ?? ($pos[0] ?? null);
        $canCreate = ! empty($payload['po_creation_available']) && $pos === [];
        $rows = $selected['items'] ?? collect($payload['items'] ?? [])->map(static fn ($item) => [
            'item_id' => (int) ($item['source_item_id'] ?? 0),
            'name' => (string) ($item['product_name'] ?? 'Product'),
            'sku' => (string) ($item['sku'] ?? ''),
            'quantity' => (int) ($item['quantity'] ?? 0),
            'supplier_unit_cost' => $item['unit_price'] ?? null,
        ])->all();
        $editing = $selected !== null && $request->query('edit') === '1';
        $creating = $canCreate && $request->query('create') === '1';
        $tracking = collect($payload['documents'] ?? [])->first(static fn ($doc) => ($doc['kind'] ?? null) === 'tracking_pdf');
        return view('portal.purchase-orders.show', $this->shared($request, $dashboardData) + compact(
            'order', 'payload', 'pos', 'selected', 'rows', 'tracking', 'canCreate', 'editing', 'creating'
        ));
    }

    public function save(Request $request, int $id, SupplierScope $scope, FlowtrackPurchaseOrderDelivery $delivery)
    {
        $order = $this->order($request, $id, $scope);
        $data = $order->payload ?? [];
        $pos = array_values($data['purchase_orders'] ?? []);
        $valid = Validator::make($request->all(), [
            'po_id' => ['nullable', 'integer', 'min:1'],
            'revision' => ['nullable', 'integer', 'min:1'],
            'mode' => ['nullable', 'in:single,split'],
            'split_item_ids' => ['sometimes', 'array', 'max:100'],
            'split_item_ids.*' => ['integer', 'min:1', 'distinct'],
            'template' => ['sometimes', 'array', 'max:30'],
            'template.*' => ['nullable', 'string', 'max:255'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999999999'],
            'items.*.supplier_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
        ])->validate();
        $poId = (int) ($valid['po_id'] ?? 0);
        $existing = collect($pos)->first(static fn ($po) => (int) ($po['id'] ?? 0) === $poId);
        $creating = $poId === 0;
        if ($creating) {
            abort_unless(! empty($data['po_creation_available']) && $pos === [], 409, 'PO creation is not available. Refresh the order.');
        } else {
            abort_unless($existing, 404);
            abort_unless((int) ($valid['revision'] ?? 0) === (int) $existing['revision'], 409, 'The PO was updated. Refresh before editing.');
        }
        $allowed = collect($creating ? ($data['items'] ?? []) : ($existing['items'] ?? []))
            ->map(static fn ($line) => (int) ($line[$creating ? 'source_item_id' : 'item_id'] ?? 0))
            ->filter(static fn ($value) => $value > 0)->values()->all();
        $submitted = collect($valid['items'])->pluck('item_id')->map(static fn ($value) => (int) $value)->all();
        abort_unless($allowed && count($allowed) === count($submitted) && ! array_diff($allowed, $submitted)
            && ! array_diff($submitted, $allowed), 403, 'Product scope mismatch. Refresh the order.');
        $splitIds = array_map('intval', (array) ($valid['split_item_ids'] ?? []));
        if ($creating) {
            $mode = (string) ($valid['mode'] ?? 'single');
            if ($mode === 'split' && (count($allowed) < 2 || $splitIds === [])) {
                throw ValidationException::withMessages(['mode' => 'Select at least one product to split into its own PO.']);
            }
            if (array_diff($splitIds, $allowed)) {
                throw ValidationException::withMessages(['split_item_ids' => 'Choose products assigned to your company.']);
            }
        }
        $source = (int) ($data['source_id'] ?? 0);
        $supplier = (int) ($request->user()->company->flowtrack_supplier_id ?? 0);
        abort_unless($source > 0 && $supplier > 0, 403);
        try {
            $result = $delivery->save([
                'supplier_id' => $supplier, 'order_id' => $source,
                'action' => $creating ? 'create' : 'update',
                'po_id' => $creating ? null : $poId,
                'revision' => $creating ? null : (int) $valid['revision'],
                'mode' => $creating ? ($valid['mode'] ?? 'single') : null,
                'split_item_ids' => $creating ? $splitIds : [],
                'template' => $creating ? ($valid['template'] ?? []) : [],
                'delivery_date' => $valid['delivery_date'] ?? null,
                'items' => array_values($valid['items']),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            report($exception);
            return back()->withInput()->withErrors(['purchase_order' => 'FlowTracker is unreachable. Your changes have not been confirmed; refresh the page before retrying.']);
        } catch (RuntimeException $exception) {
            report($exception);
            return back()->withInput()->withErrors(['purchase_order' => $exception->getMessage()]);
        }
        if ($result['status'] === 409 || $result['status'] === 403 || $result['status'] === 404) {
            return back()->withInput()->withErrors(['purchase_order' => 'This Purchase Order has changed, is no longer available, or the supplier assignment changed. Reload before editing again.']);
        }
        if ($result['status'] === 422) {
            return back()->withInput()->withErrors(['purchase_order' => (string) ($result['data']['message'] ?? 'FlowTracker rejected these changes. Check the PO details.')]);
        }
        if ($result['status'] < 200 || $result['status'] >= 300 || ($result['data']['received'] ?? false) !== true
            || ! is_array($result['data']['po'] ?? null)) {
            return back()->withInput()->withErrors(['purchase_order' => 'FlowTracker did not confirm the save. Refresh before trying again.']);
        }
        $accepted = $result['data']['po'];
        $acceptedRows = $creating ? ($result['data']['pos'] ?? [$accepted]) : [$accepted];
        abort_unless(is_array($acceptedRows) && $acceptedRows !== [], 502, 'FlowTracker returned no PO records.');
        // Optimistically display the authoritative response without waiting for
        // the queued projection. Do not overwrite a newer incoming revision.
        DB::transaction(function () use ($order, $acceptedRows): void {
            $locked = SupplierResource::query()->whereKey($order->id)
                ->where('supplier_company_id', $order->supplier_company_id)->lockForUpdate()->firstOrFail();
            $payload = $locked->payload ?? [];
            $pos = array_values($payload['purchase_orders'] ?? []);
            foreach ($acceptedRows as $accepted) {
                $old = collect($pos)->first(static fn ($p) => (int) ($p['id'] ?? 0) === (int) $accepted['id']);
                if (! $old || (int) ($old['revision'] ?? 0) <= (int) $accepted['revision']) {
                    $pos = array_values(array_filter($pos, static fn ($po) => (int) ($po['id'] ?? 0) !== (int) $accepted['id']));
                    $pos[] = $accepted;
                }
            }
            $payload['purchase_orders'] = $pos;
            $payload['po_creation_available'] = false;
            $locked->payload = $payload;
            $locked->save();
            $locked->company()->increment('dashboard_revision');
        });
        return redirect()->route('supplier.purchase-orders.show', ['id' => $id, 'po' => (int) $accepted['id']])
            ->with('success', $creating ? 'Purchase Order created in FlowTracker.' : 'Purchase Order updated in FlowTracker.');
    }

    public function download(Request $request, int $id, int $po, int $document, SupplierScope $scope, FlowtrackPurchaseOrderDelivery $delivery)
    {
        $order = $this->order($request, $id, $scope);
        $record = collect($order->payload['purchase_orders'] ?? [])
            ->first(static fn ($row) => (int) ($row['id'] ?? 0) === $po);
        $file = $record['document'] ?? null;
        abort_unless($file && (int) ($file['id'] ?? 0) === $document && (int) ($file['size'] ?? 0) > 0
            && (int) ($file['size'] ?? 0) <= 10485760, 404);
        $supplier = (int) $request->user()->company->flowtrack_supplier_id;
        $source = (int) ($order->payload['source_id'] ?? 0);
        abort_unless($supplier > 0 && $source > 0, 404);
        try {
            [$base, $secret] = $delivery->endpoint();
            $time = (string) time();
            $response = Http::connectTimeout(4)->timeout(30)->withoutRedirecting()->withHeaders([
                'X-FlowTrack-Timestamp' => $time,
                'X-FlowTrack-Supplier' => (string) $supplier,
                'X-FlowTrack-Signature' => hash_hmac('sha256', implode("\n", [$time, $supplier, $source, $po, $document]), $secret),
                'Accept' => 'application/pdf',
            ])->withOptions(['stream' => true])->get($base.'/api/supplier-bridge/purchase-order-file/'.$source.'/'.$po.'/'.$document);
            abort_unless($response->successful(), 404);
            $expected = (int) $file['size'];
            $temporary = tmpfile();
            abort_unless(is_resource($temporary), 503);
            try {
                $body = $response->toPsrResponse()->getBody();
                $received = 0;
                while (! $body->eof()) {
                    $chunk = $body->read(65536);
                    if ($chunk === '') break;
                    $received += strlen($chunk);
                    abort_if($received > $expected, 502);
                    abort_unless(fwrite($temporary, $chunk) === strlen($chunk), 502);
                }
                abort_unless($received === $expected, 502);
                rewind($temporary);
            } catch (\Throwable $exception) {
                fclose($temporary);
                throw $exception;
            }
            $name = basename(str_replace('\\', '/', (string) $file['name'])) ?: 'purchase-order.pdf';
            return response()->streamDownload(static function () use ($temporary): void {
                try { fpassthru($temporary); } finally { fclose($temporary); }
            }, $name, [
                'Content-Type' => 'application/pdf', 'Content-Length' => (string) $expected,
                'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $exception) {
            report($exception);
            abort(503, 'PO document is temporarily unavailable.');
        }
    }

    private function order(Request $request, int $id, SupplierScope $scope): SupplierResource
    {
        $order = $scope->resource($request, $id, 'order');
        abort_if($order->status === 'revoked', 404);
        return $order;
    }

    private function shared(Request $request, SupplierDashboardData $dashboardData): array
    {
        return ['company' => $request->user()->company, 'user' => $request->user(),
            'hasUnreadNotifications' => $dashboardData->hasUnreadNotifications($request->user())];
    }
}
