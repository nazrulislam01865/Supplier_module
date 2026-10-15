<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuotation extends Model
{
    protected $fillable = ['supplier_company_id','supplier_resource_id','supplier_user_id','status','data','total_minor_units','submitted_at','submission_uuid','sync_status','sync_attempts','sync_next_attempt_at','sync_locked_at','synced_at','sync_error'];
    protected function casts(): array { return ['data'=>'array','submitted_at'=>'datetime','sync_next_attempt_at'=>'datetime','sync_locked_at'=>'datetime','synced_at'=>'datetime']; }
    public function resource(): BelongsTo { return $this->belongsTo(SupplierResource::class, 'supplier_resource_id'); }
}
