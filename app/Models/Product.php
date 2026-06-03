<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'product_code',
        'product_name',
        'unit',
        'cost_price',
        'stock',
        'status',
    ];

    public function prices()
    {
        return $this->hasMany(ProductPrice::class)->with('customerGroup');
    }

    public function salesDetails()
    {
        return $this->hasMany(SalesDetail::class);
    }

    public function stockMutations()
    {
        return $this->hasMany(StockMutation::class);
    }
}
