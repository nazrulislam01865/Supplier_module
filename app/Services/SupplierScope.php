<?php
namespace App\Services;

use App\Models\SupplierResource;
use App\Models\SupplierQuotation;
use Illuminate\Http\Request;

class SupplierScope
{
    public function resource(Request $request, int $id, string $type): SupplierResource
    {
        return SupplierResource::query()
            ->where('supplier_company_id', $request->user()->supplier_company_id)
            ->where('type', $type)->findOrFail($id);
    }
    public function quotation(Request $request, SupplierResource $resource): ?SupplierQuotation
    {
        return SupplierQuotation::query()
            ->where('supplier_company_id', $request->user()->supplier_company_id)
            ->where('supplier_resource_id', $resource->id)->first();
    }
}
