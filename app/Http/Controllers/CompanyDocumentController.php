<?php
namespace App\Http\Controllers;

use App\Models\SupplierDocument;
use App\Services\SupplierFileSecurity;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyDocumentController extends Controller
{
    public function __construct(private SupplierFileSecurity $security) {}

    private const TYPES = [
        'Business registration','Tax certificate','Factory audit report',
        'Bank information','Quality certification','Supplier agreement','Other',
    ];

    public function index(Request $request)
    {
        return response()->json(['documents'=>SupplierDocument::query()
            ->where('supplier_company_id', $request->user()->supplier_company_id)
            ->whereNull('supplier_resource_id')->where('scan_status','clean')
            ->latest()->limit(100)->get(['id','document_type','original_name','size','expires_at'])]);
    }

    public function upload(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'file'=>['required','file','max:20480','mimes:pdf,xlsx,docx,jpg,jpeg,png'],
            'document_type'=>['required',Rule::in(self::TYPES)],
            'expires_at'=>['nullable','date_format:Y-m-d'],
        ]);
        $file=$data['file'];
        $this->security->assertClean($file);
        $companyId=$request->user()->supplier_company_id;
        $path=$file->store('supplier/'.$companyId.'/company', 'private');
        try {
            $document=SupplierDocument::create([
                'supplier_company_id'=>$companyId,'uploaded_by'=>$request->user()->id,
                'document_type'=>$data['document_type'],'original_name'=>basename($file->getClientOriginalName()),
                'storage_path'=>$path,'mime_type'=>$file->getMimeType(),'size'=>$file->getSize(),
                'scan_status'=>'clean','expires_at'=>$data['expires_at'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('private')->delete($path);
            throw $exception;
        }
        return response()->json(['message'=>'Company document saved securely.', 'id'=>$document->id], 201);
    }

    public function remove(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $document=SupplierDocument::query()
            ->where('supplier_company_id',$request->user()->supplier_company_id)
            ->whereNull('supplier_resource_id')->findOrFail($id);
        Storage::disk('private')->delete($document->storage_path);
        $document->delete();
        return response()->json(['message'=>'Company document removed.']);
    }
}
