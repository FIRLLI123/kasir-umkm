<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesDetail extends Model
{
    use HasFactory;

    protected $table = 'sales_d';

    protected $fillable = [
        'sales_h_id',
        'product_id',
        'product_name_snapshot',
        'qty',
        'cost_price',
        'selling_price',
        'subtotal',
        'margin',
        'status',
    ];

    public function salesHeader()
    {
        return $this->belongsTo(SalesHeader::class, 'sales_h_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
