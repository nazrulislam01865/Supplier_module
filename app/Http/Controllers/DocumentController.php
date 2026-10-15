<?php
namespace App\Http\Controllers;

use App\Models\SupplierDocument;
use App\Models\SupplierQuotation;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\SupplierFileSecurity;

class DocumentController extends Controller
{
    public function __construct(private SupplierScope $scope, private SupplierFileSecurity $security) {}

    public function upload(Request $request, int $id)
    {
        $rfq = $this->scope->resource($request,$id,'rfq');
        if (!in_array($rfq->status,['open','draft'],true) || $rfq->due_at?->isPast()) abort(409, 'Document uploads are closed.');
        $data = $request->validate([
            'file'=>['required','file','max:20480','mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,gif,zip'],
            'document_type'=>['required',Rule::in(['Formal quotation','Price breakdown','Product specification','Supporting document'])],
        ]);
        abort_if(SupplierDocument::query()->where('supplier_company_id',$request->user()->supplier_company_id)
            ->where('supplier_resource_id',$rfq->id)->count() >= 10,422,'Maximum ten RFQ documents are allowed.');
        $uploaded = $data['file'];
        $this->security->assertClean($uploaded);
        $path = $uploaded->store('supplier/'. $request->user()->supplier_company_id .'/rfq/'. $rfq->id, 'private');
        try {
            $doc = SupplierDocument::create([
                'supplier_company_id'=>$request->user()->supplier_company_id,
                'supplier_resource_id'=>$rfq->id,
                'supplier_quotation_id'=>$this->scope->quotation($request,$rfq)?->id,
                'uploaded_by'=>$request->user()->id,
                'document_type'=>$data['document_type'],
                'original_name'=>basename($uploaded->getClientOriginalName()),
                'storage_path'=>$path,
                'mime_type'=>$uploaded->getMimeType(),
                'size'=>$uploaded->getSize(),
                'scan_status'=>'clean',
            ]);
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($path);
            throw $e;
        }
        return response()->json(['message'=>'Document uploaded and scanned.','document'=>[
            'id'=>$doc->id,'original_name'=>$doc->original_name,'document_type'=>$doc->document_type,'size'=>$doc->size
        ]], 201);
    }

    private function scopedDoc(Request $request, int $id): SupplierDocument
    {
        return SupplierDocument::query()->where('supplier_company_id',$request->user()->supplier_company_id)
            ->where('scan_status','clean')->findOrFail($id);
    }
    public function download(Request $request, int $id)
    {
        $doc = $this->scopedDoc($request,$id);
        abort_unless(Storage::disk('private')->exists($doc->storage_path),404);
        return Storage::disk('private')->download($doc->storage_path, $doc->original_name, [
            'Content-Type'=>'application/octet-stream',
            'X-Content-Type-Options'=>'nosniff',
            'Cache-Control'=>'private, no-store',
        ]);
    }
    public function remove(Request $request, int $id)
    {
        $doc = $this->scopedDoc($request,$id);
        if ($doc->supplier_resource_id) {
            $rfq = $this->scope->resource($request,$doc->supplier_resource_id,'rfq');
            if (!in_array($rfq->status,['open','draft'],true) || $rfq->due_at?->isPast()) abort(409,'Submitted attachments cannot be removed.');
        }
        Storage::disk('private')->delete($doc->storage_path);
        $doc->delete();
        return response()->json(['message'=>'Document removed.']);
    }
}
