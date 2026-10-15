<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierCompany extends Model
{
    protected $fillable = ['reference','name','active','profile','encrypted_banking','flowtrack_supplier_id','flowtrack_short_code','flowtrack_brand_name','flowtrack_brand_logo_url','flowtrack_brand_favicon_url','flowtrack_portal_footer'];
    protected function casts(): array { return ['flowtrack_supplier_id'=>'integer','active'=>'boolean','profile'=>'array','encrypted_banking'=>'encrypted:array','flowtrack_portal_footer'=>'array']; }
    public function users(): HasMany { return $this->hasMany(SupplierUser::class); }
    public function resources(): HasMany { return $this->hasMany(SupplierResource::class); }
}
