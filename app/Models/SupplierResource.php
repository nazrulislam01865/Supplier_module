<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierResource extends Model
{
    protected $fillable = ['supplier_company_id','type','external_reference','status','payload','due_at'];
    protected function casts(): array { return ['payload'=>'array','due_at'=>'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(SupplierCompany::class, 'supplier_company_id'); }
    public function quotation(): HasOne { return $this->hasOne(SupplierQuotation::class, 'supplier_resource_id'); }
}
