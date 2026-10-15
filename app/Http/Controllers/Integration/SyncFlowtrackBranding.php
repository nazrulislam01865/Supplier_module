<?php

namespace App\Http\Controllers\Integration;

use App\Models\SupplierCompany;
use App\Support\TrustedFlowtrackBrandUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Signed, revision-guarded brand update without the costly product catalogue. */
final class SyncFlowtrackBranding extends Controller
{
    use VerifiesFlowtrack;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->signedData($request);
        if ($data === null) return response()->json(['message' => 'Unauthorized.'], 401);
        $valid = Validator::make($data, [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'revision' => ['required', 'integer', 'min:1'],
            'brand_name' => ['required', 'string', 'max:255'],
            'brand_logo_path' => ['present', 'nullable', 'regex:#^/branding-assets/logo/[A-Za-z0-9_-]+\.(?:png|jpe?g|webp)(?:/workspace/[1-9][0-9]*)?$#'],
            'brand_favicon_path' => ['sometimes', 'nullable', 'regex:#^/branding-assets/favicon/[A-Za-z0-9_-]+\.(?:ico|png|jpe?g|webp)(?:/workspace/[1-9][0-9]*)?$#'],
            'footer' => ['sometimes', 'array:copyright,links'],
            'footer.copyright' => ['present', 'nullable', 'string', 'max:255'],
            'footer.links' => ['required_with:footer', 'array:privacy,terms,help'],
            'footer.links.*' => ['required', 'array:enabled,label,url'],
            'footer.links.*.enabled' => ['required', 'boolean'],
            'footer.links.*.label' => ['required', 'string', 'max:80'],
            'footer.links.*.url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ]);
        if ($valid->fails()) return response()->json(['message' => 'Invalid branding.'], 422);
        $logo = TrustedFlowtrackBrandUrl::resolve($data['brand_logo_path']);
        // Never replace a valid logo with a broken/untrusted configured origin.
        if ($data['brand_logo_path'] !== null && $logo === null)
            return response()->json(['message' => 'Brand origin unavailable.'], 422);

        $favicon = TrustedFlowtrackBrandUrl::favicon($data['brand_favicon_path'] ?? null);
        if (! empty($data['brand_favicon_path']) && $favicon === null)
            return response()->json(['message' => 'Brand origin unavailable.'], 422);

        $found = DB::transaction(function () use ($data, $logo, $favicon): bool {
            $company = SupplierCompany::query()->where('flowtrack_supplier_id', (int) $data['supplier_id'])
                ->lockForUpdate()->first();
            if (! $company) return false;
            $revision = (int) $data['revision'];
            if ($revision < max((int) ($company->brand_revision ?? 0), (int) ($company->catalog_revision ?? 0))) return true;
            $company->flowtrack_brand_name = $data['brand_name'];
            $company->flowtrack_brand_logo_url = $logo;
            if (array_key_exists('brand_favicon_path', $data)) $company->flowtrack_brand_favicon_url = $favicon;
            if (array_key_exists('footer', $data)) $company->flowtrack_portal_footer = \App\Support\SupplierPortalFooter::normalize($data['footer']);
            $company->brand_revision = $revision;
            if ($company->isDirty(['flowtrack_brand_name', 'flowtrack_brand_logo_url', 'flowtrack_brand_favicon_url', 'flowtrack_portal_footer'])) {
                $company->dashboard_revision = ((int) $company->dashboard_revision) + 1;
            }
            $company->save();
            return true;
        });
        return $found ? response()->json(['synced' => true])
            : response()->json(['message' => 'Supplier not linked.'], 409);
    }
}
