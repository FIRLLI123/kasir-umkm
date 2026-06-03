<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'company_code',
        'address',
        'phone',
        'email',
        'logo',
        'status',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customerGroups()
    {
        return $this->hasMany(CustomerGroup::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function appSettings()
    {
        return $this->hasMany(AppSetting::class);
    }
}
