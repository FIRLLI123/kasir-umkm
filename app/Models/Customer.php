<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_code',
        'customer_name',
        'phone',
        'address',
        'customer_group_id',
        'status',
    ];

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    public function sales()
    {
        return $this->hasMany(SalesHeader::class, 'customer_id');
    }
}
