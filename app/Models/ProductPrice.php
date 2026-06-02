<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'customer_group_id',
        'selling_price',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}
