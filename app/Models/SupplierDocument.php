<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SupplierDocument extends Model
{
    protected $fillable = ['supplier_company_id','supplier_resource_id','supplier_quotation_id','uploaded_by','document_type','original_name','storage_path','mime_type','size','scan_status','expires_at'];
}
