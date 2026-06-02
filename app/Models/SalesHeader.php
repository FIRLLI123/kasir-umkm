<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesHeader extends Model
{
    use HasFactory;

    protected $table = 'sales_h';

    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'user_id',
        'customer_id',
        'customer_group_id',
        'payment_method_id',
        'subtotal',
        'discount',
        'grand_total',
        'total_modal',
        'total_margin',
        'paid_amount',
        'change_amount',
        'status',
        'void_reason',
        'void_by',
        'void_at',
    ];

    protected $casts = [
        'invoice_date' => 'datetime',
        'void_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function details()
    {
        return $this->hasMany(SalesDetail::class, 'sales_h_id');
    }

    public function voidUser()
    {
        return $this->belongsTo(User::class, 'void_by');
    }
}
