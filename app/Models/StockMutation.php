<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMutation extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'stock_mutations';

    protected $fillable = [
        'company_id',
        'product_id',
        'mutation_date',
        'mutation_type',
        'reference_type',
        'reference_id',
        'qty_in',
        'qty_out',
        'stock_before',
        'stock_after',
        'note',
        'created_by',
        'status',
    ];

    protected $casts = [
        'mutation_date' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
