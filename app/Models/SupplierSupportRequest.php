<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupplierSupportRequest extends Model
{
    protected $fillable = ['supplier_company_id','supplier_user_id','category','reference','message','status'];
}
