<?php
namespace App\Http\Controllers;

use App\Models\SupplierSupportRequest;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'category'=>['required',Rule::in(['rfq','order','document','bill','profile'])],
            'reference'=>['nullable','string','max:120'],
            'message'=>['required','string','min:5','max:5000'],
        ]);
        SupplierSupportRequest::create($data + [
            'supplier_company_id'=>$request->user()->supplier_company_id,
            'supplier_user_id'=>$request->user()->id,
            'status'=>'open',
        ]);
        return response()->json(['message'=>'Your support request was recorded.'], 201);
    }
}
