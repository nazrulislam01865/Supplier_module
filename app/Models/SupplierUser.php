<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierUser extends Authenticatable
{
    use Notifiable;
    protected $table = 'supplier_users';
    protected $fillable = ['supplier_company_id','name','email','username','password','role','active','must_change_password'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['password'=>'hashed','active'=>'boolean','must_change_password'=>'boolean','notifications_read_at'=>'datetime']; }
    public function company(): BelongsTo { return $this->belongsTo(SupplierCompany::class, 'supplier_company_id'); }
}
