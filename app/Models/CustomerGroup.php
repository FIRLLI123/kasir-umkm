<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'group_code',
        'group_name',
        'status',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function productPrices()
    {
        return $this->hasMany(ProductPrice::class);
    }
}
