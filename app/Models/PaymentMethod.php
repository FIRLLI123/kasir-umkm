<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'method_code',
        'method_name',
        'status',
    ];

    public function sales()
    {
        return $this->hasMany(SalesHeader::class);
    }
}
