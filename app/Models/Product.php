<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
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
