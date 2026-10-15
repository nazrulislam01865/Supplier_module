<?php
namespace App\Http\Controllers;

use App\Models\SupplierQuotation;
use App\Models\SupplierDocument;
use App\Models\SupplierResource;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationController extends Controller
{
    public function __construct(private SupplierScope $scope) {}

    public function show(Request $request, int $id)
    {
        $rfq = $this->scope->resource($request, $id, 'rfq');
        $quote = $this->scope->quotation($request, $rfq);
        $docs = SupplierDocument::query()
            ->where('supplier_company_id', $request->user()->supplier_company_id)
            ->where('supplier_resource_id', $rfq->id)
            ->where('scan_status','clean')
            ->get(['id','document_type','original_name','size','mime_type']);
        return response()->json(['rfq'=>['id'=>$rfq->id,'status'=>$rfq->status,'payload'=>$rfq->payload], 'quotation'=>$quote ? [
            'status'=>$quote->status,'data'=>$quote->data,'submitted_at'=>$quote->submitted_at,
            'sync_status'=>$quote->sync_status, 'sync_attempts'=>$quote->sync_attempts,
            'sync_error'=>$quote->sync_error,
        ] : null, 'documents'=>$docs]);
    }

    private function validatedData(Request $request, bool $final = true): array
    {
        $required = $final ? 'required' : 'nullable';
        return $request->validate([
            'contact'=>[$required,'string','max:120'],
            'email'=>[$required,'email','max:254'],
            'phone'=>[$required,'string','max:60'],
            'currency'=>[$required,Rule::in(['USD','EUR','CNY','GBP','BDT'])],
            'unit_price'=>['nullable','numeric','gt:0','max:100000000'],
            'moq'=>['nullable','integer','min:1','max:100000000'],
            'tooling'=>[$required,'numeric','min:0','max:100000000'],
            'sample'=>[$required,'numeric','min:0','max:100000000'],
            'discount'=>[$required,'numeric','min:0','max:100000000'],
            'tax'=>[$required,Rule::in(['Included','Excluded'])],
            'production_lead'=>[$required,'integer','min:1','max:3650'],
            'sample_lead'=>[$required,'integer','min:1','max:3650'],
            'incoterm'=>[$required,Rule::in(['FOB','CIF','EXW','DDP'])],
            'shipping_port'=>[$required,'string','max:150'],
            'delivery_date'=>[$required,'date_format:Y-m-d'],
            'validity'=>[$required,'integer','min:1','max:365'],
            'compliance'=>[$required,Rule::in(['Yes, fully compliant','Partially compliant','No - alternative proposed'])],
            'deviations'=>['nullable','string','max:4000'],
            'notes'=>['nullable','string','max:4000'],
            'document_notes'=>['nullable','string','max:4000'],
            'supporting_information'=>['nullable','array','max:3'],
            'supporting_information.*'=>[Rule::in(['Product certification','Factory audit report','Sample images'])],
            'item_prices'=>['nullable','array','max:100'],
            'item_prices.*.item_id'=>['required','integer','min:1','distinct'],
            'item_prices.*.unit_price'=>['nullable','numeric','gt:0','max:100000000'],
            'item_prices.*.moq'=>['nullable','integer','min:1','max:100000000'],
            'price_breaks'=>['nullable','array','max:0'],
        ]);
    }

    public function save(Request $request, int $id)
    {
        $rfq = $this->scope->resource($request, $id, 'rfq');
        $data = $this->validatedData($request,false);
        if (!in_array($rfq->status, ['open','draft'], true)) abort(409, 'This RFQ is no longer editable.');
        if ($rfq->due_at?->isPast()) abort(409, 'The quotation deadline has passed.');
        $quote = DB::transaction(function () use ($request, $rfq, $data) {
            SupplierResource::where('supplier_company_id', $request->user()->supplier_company_id)->where('type','rfq')->lockForUpdate()->findOrFail($rfq->id);
            $record = SupplierQuotation::query()
                ->where('supplier_company_id', $request->user()->supplier_company_id)
                ->where('supplier_resource_id', $rfq->id)->lockForUpdate()->first();
            if ($record && $record->status !== 'draft') abort(409, 'Submitted quotations cannot be edited.');
            $record ??= new SupplierQuotation([
                'supplier_company_id'=>$request->user()->supplier_company_id,
                'supplier_resource_id'=>$rfq->id,
            ]);
            $record->fill(['supplier_user_id'=>$request->user()->id,'status'=>'draft','data'=>$data]);
            $record->save();
            $rfq->refresh();
            if (!in_array($rfq->status,['open','draft'],true) || $rfq->due_at?->isPast()) abort(409,'RFQ has changed.');
            $rfq->update(['status'=>'draft']);
            $request->user()->company()->increment('dashboard_revision');
            return $record;
        });
        return response()->json(['message'=>'Quotation draft saved.','id'=>$quote->id]);
    }

    public function submit(Request $request, int $id)
    {
        $rfq = $this->scope->resource($request, $id, 'rfq');
        $data = $this->validatedData($request);
        $request->validate(['accuracy'=>['accepted'],'authority'=>['accepted']]);
        if ($rfq->due_at?->isPast()) abort(409, 'The quotation deadline has passed.');
        if (!in_array($rfq->status, ['open','draft'], true)) abort(409, 'This RFQ is not open for submission.');
        $items = $rfq->payload['items'] ?? [];
        if (count($items) > 1) {
            $expected = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $actual = collect($data['item_prices'] ?? [])->pluck('item_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($actual !== $expected) {
                throw ValidationException::withMessages(['item_prices' => 'Supply a price and MOQ for every requested product.']);
            }
            $quoted = collect($data['item_prices'])->keyBy('item_id');
            $subtotal = 0;
            foreach ($items as $item) {
                $line = $quoted[(int) $item['id']];
                if (! isset($line['unit_price'], $line['moq'])) {
                    throw ValidationException::withMessages(['item_prices' => 'Unit price and MOQ are required for every product.']);
                }
                $qty100 = (int) round((float) $item['quantity'] * 100);
                if ($qty100 < 1 || $qty100 > 10000000000) abort(409, 'RFQ quantity is invalid.');
                $unit = self::minor($line['unit_price']);
                if ($qty100 > intdiv(PHP_INT_MAX - 50, max(1, $unit))) {
                    throw ValidationException::withMessages(['item_prices' => 'Quoted value is too large.']);
                }
                $part = intdiv($qty100 * $unit + 50, 100);
                if ($subtotal > 100000000000000 - $part) {
                    throw ValidationException::withMessages(['item_prices' => 'Quoted value is too large.']);
                }
                $subtotal += $part;
            }
        } else {
            if (! isset($data['unit_price'], $data['moq'])) {
                throw ValidationException::withMessages(['unit_price' => 'Unit price and MOQ are required.']);
            }
            $qty = (float) ($items[0]['quantity'] ?? $rfq->payload['quantity'] ?? 0);
            $qty100 = (int) round($qty * 100);
            if ($qty100 < 1 || $qty100 > 10000000000) abort(409, 'RFQ quantity is invalid.');
            $unit = self::minor($data['unit_price']);
            if ($qty100 > intdiv(PHP_INT_MAX - 50, max(1, $unit))) {
                throw ValidationException::withMessages(['unit_price' => 'Quoted value is too large.']);
            }
            $subtotal = intdiv($qty100 * $unit + 50, 100);
            if ($subtotal > 100000000000000) {
                throw ValidationException::withMessages(['unit_price' => 'Quoted value is too large.']);
            }
        }
        $tooling = self::minor($data['tooling']);
        $sample = self::minor($data['sample']);
        $discount = self::minor($data['discount']);
        if ($discount > $subtotal + $tooling + $sample) {
            throw ValidationException::withMessages(['discount'=>'Discount cannot exceed the quotation total.']);
        }
        $quotationId = DB::transaction(function () use ($request,$rfq,$data,$subtotal,$tooling,$sample,$discount) {
            // Lock the RFQ first to make simultaneous submissions safe.
            $locked = SupplierResource::query()
                ->where('supplier_company_id',$request->user()->supplier_company_id)
                ->where('type','rfq')->lockForUpdate()->findOrFail($rfq->id);
            if (!in_array($locked->status,['open','draft'],true) || $locked->due_at?->isPast()) abort(409, 'RFQ has changed.');
            $quote = SupplierQuotation::firstOrNew([
                'supplier_company_id'=>$request->user()->supplier_company_id,
                'supplier_resource_id'=>$rfq->id,
            ]);
            if ($quote->exists && $quote->status !== 'draft') abort(409, 'Quotation already finalized.');
            $quote->fill([
                'supplier_user_id'=>$request->user()->id,
                'status'=>'submitted', 'data'=>$data,
                'total_minor_units'=>$subtotal+$tooling+$sample-$discount,
                'submitted_at'=>now(), 'submission_uuid'=>(string) Str::uuid(),
                'sync_status'=>'pending', 'sync_attempts'=>0,
                'sync_next_attempt_at'=>null, 'sync_error'=>null,
            ])->save();
            $locked->update(['status'=>'submitted']);
            $request->user()->company()->increment('dashboard_revision');
            return (int) $quote->id;
        });
        try { \App\Jobs\DeliverSupplierQuotation::dispatch($quotationId)->onQueue('supplier-deliveries'); }
        catch (\Throwable $exception) { report($exception); }
        // Keep the supplier's browser responsive even for ten 20 MB documents.
        // The durable outbox is processed by the existing Laravel scheduler.
        return response()->json([
            'message'=>'Quotation saved securely. Delivery to FlowTracker is queued, including all attachments.',
            'sync_status'=>'pending',
        ]);
    }

    public function decline(Request $request, int $id)
    {
        $rfq = $this->scope->resource($request,$id,'rfq');
        $data = $request->validate([
            'reason'=>['required',Rule::in(['Unable to meet requested delivery date','Product outside our capability','Quantity not suitable','Other'])],
            'note'=>['nullable','string','max:2000'],
        ]);
        DB::transaction(function () use ($request, $rfq, $data) {
            $locked = SupplierResource::where('supplier_company_id',$request->user()->supplier_company_id)
                ->where('type','rfq')->lockForUpdate()->findOrFail($rfq->id);
            if (!in_array($locked->status,['open','draft'],true)) abort(409, 'RFQ is not open.');
            $quote = SupplierQuotation::firstOrNew([
                'supplier_company_id'=>$request->user()->supplier_company_id,
                'supplier_resource_id'=>$rfq->id,
            ]);
            $quote->fill(['supplier_user_id'=>$request->user()->id,'status'=>'declined', 'data'=>['decline'=>$data]])->save();
            $locked->update(['status'=>'declined']);
            $request->user()->company()->increment('dashboard_revision');
        });
        return response()->json(['message'=>'Decline recorded in supplier portal; buyer sync is not yet connected.']);
    }

    private static function minor($number): int
    {
        // String arithmetic avoids binary float errors for persisted money.
        $value = (string) $number;
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['pricing'=>'Use no more than 2 decimal places.']);
        }
        [$whole,$fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ((int)$whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
