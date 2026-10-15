<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class SupplierPriceProposal extends Model
{
    protected $fillable = [
        'supplier_company_id', 'supplier_resource_id', 'supplier_user_id',
        'request_uuid', 'base_version', 'proposed_prices', 'status', 'delivery_status',
        'attempts', 'next_attempt_at', 'review_note',
    ];
    protected function casts(): array
    {
        return ['proposed_prices' => 'array', 'next_attempt_at' => 'datetime'];
    }
}
