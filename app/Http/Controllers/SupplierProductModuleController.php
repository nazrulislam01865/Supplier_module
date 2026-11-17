<?php

namespace App\Http\Controllers;

use App\Services\FlowtrackProductModuleBridge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

/** Product pages are native Supplier Portal Blade views, never an iframe. */
final class SupplierProductModuleController extends Controller
{
    public function __invoke(Request $request, FlowtrackProductModuleBridge $bridge): View|Response
    {
        $group = (string) $request->query('group', 'product');
        abort_unless(in_array($group, ['product', 'product_category'], true), 404);
        $create = $request->query('create') === '1';
        $edit = $request->query('edit') === '1';
        $id = filter_var($request->query('id', 0), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
        $error = null;
        $status = 503;
        $catalog = [];
        $details = [];
        $lookups = [];
        try {
            if ($group === 'product' && $id) {
                $details = $bridge->native($request->user(), 'detail', ['id' => $id]);
            } elseif (! $create || $group === 'product_category') {
                $catalog = $bridge->native($request->user(), 'catalog', [
                    'group' => $group, 'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
                    'page' => max(1, (int) $request->query('page', 1)),
                    'per_page' => in_array((int) $request->query('per_page', 20), [10,20,50,100], true) ? (int) $request->query('per_page', 20) : 20,
                    'status' => in_array($request->query('status'), ['active','inactive'], true) ? $request->query('status') : null,
                    'category_id' => max(0, (int) $request->query('category_id', 0)) ?: null,
                    'main_category' => mb_substr(trim((string) $request->query('main_category','')), 0, 100),
                    'availability' => in_array($request->query('availability'), ['all','specific'], true) ? $request->query('availability') : null,
                ]);
            }
            if ($create || $edit || $id) {
                $lookups = $bridge->native($request->user(), 'lookups');
            }
            $permissions = $details['permissions'] ?? $catalog['permissions'] ?? $lookups['permissions'] ?? [];
            $section = $group === 'product_category' ? 'categories' : 'products';
            if ($create && ! data_get($permissions, $section.'.create', false)) {
                abort(403, 'FlowTracker has not enabled product/category creation for suppliers.');
            }
            if ($edit) {
                $canEditRecord = (bool) data_get($permissions, $section.'.edit', false);
                $canEditSubsection = $group === 'product' && collect([
                    'supplier_pricing', 'client_pricing', 'options', 'documents', 'shipment', 'availability',
                ])->contains(fn (string $name) => (bool) data_get($permissions, $name.'.edit', false));
                if (! $canEditRecord && ! $canEditSubsection) {
                    abort(403, 'FlowTracker has not enabled product/category editing for suppliers.');
                }
            }
        } catch (HttpExceptionInterface $e) {
            $error = $e->getMessage();
            $status = $e->getStatusCode();
        } catch (ConnectionException $e) {
            report($e);
            $error = 'FlowTracker is unreachable. Confirm the buyer API URL and that FlowTracker is running.';
        } catch (\Throwable $e) {
            report($e);
            $error = 'Unable to load the Product workspace. Inspect both Laravel application logs.';
        }
        $data = [
            'company' => $request->user()->company, 'user' => $request->user(),
            'group' => $group, 'create' => $create, 'edit' => $edit, 'id' => $id,
            'moduleError' => $error, 'catalog' => $catalog,
            'details' => $details, 'lookups' => $lookups,
            'permissions' => $details['permissions'] ?? $catalog['permissions'] ?? $lookups['permissions'] ?? [],
        ];
        return $error ? response()->view('portal.product-module', $data, $status)
            : view('portal.product-module', $data);
    }

    public function action(Request $request, FlowtrackProductModuleBridge $bridge): RedirectResponse
    {
        $operation = (string) $request->input('operation');
        abort_unless(in_array($operation, ['save_product', 'delete_product', 'save_category', 'delete_category', 'save_pricing', 'upload_asset'], true), 404);
        $id = (int) $request->input('id', 0);
        $group = in_array($request->input('group'), ['product', 'product_category'], true)
            ? $request->input('group') : 'product';
        $data = $request->except(['_token', 'operation', 'group', 'id', 'file']);
        if ($operation === 'upload_asset') {
            $request->validate(['file' => ['required', 'file', 'max:5120'], 'kind' => ['required', 'in:image,certificate,template,option_image']]);
            $file = $request->file('file');
            $data['content'] = base64_encode(file_get_contents($file->getRealPath()));
            $data['filename'] = $file->getClientOriginalName();
        }
        try {
            $result = $bridge->native($request->user(), $operation, ['id' => $id, 'data' => $data]);
            $redirect = ['group' => $group];
            if ($group === 'product' && ! in_array($operation, ['delete_product'], true)) {
                $redirect['id'] = (int) ($result['id'] ?? $id);
            }
            return redirect()->route('supplier.products.module', $redirect)
                ->with('success', (string) ($result['message'] ?? 'Saved successfully.'));
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 401 || $e->getStatusCode() === 403) {
                return back()->withErrors(['product' => $e->getMessage()]);
            }
            return back()->withInput($request->except('file'))->withErrors(['product' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withInput($request->except('file'))->withErrors([
                'product' => 'The Product operation could not reach FlowTracker. No change was confirmed.',
            ]);
        }
    }

    public function asset(Request $request, FlowtrackProductModuleBridge $bridge, int $product, string $kind): Response
    {
        abort_unless(in_array($kind, ['image', 'option_image', 'certificate', 'template'], true), 404);
        $result = $bridge->native($request->user(), 'asset', [
            'id' => $product, 'data' => ['kind' => $kind, 'option_key' => (string) $request->query('option_key', '')],
        ]);
        $bytes = base64_decode((string) ($result['content'] ?? ''), true);
        abort_unless($bytes !== false && strlen($bytes) <= 6500000, 404);
        $type = in_array($kind, ['image', 'option_image'], true) ? (string) ($result['content_type'] ?? 'image/jpeg')
            : 'application/octet-stream';
        return response($bytes, 200, [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => in_array($kind, ['image', 'option_image'], true) ? 'inline' : 'attachment; filename="product-document"',
        ]);
    }
}
