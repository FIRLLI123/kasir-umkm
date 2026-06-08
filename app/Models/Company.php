<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'company_name',
        'company_code',
        'address',
        'phone',
        'email',
        'logo',
        'status',
        'subscription_status',
        'trial_starts_at',
        'trial_ends_at',
        'subscription_starts_at',
        'subscription_ends_at',
        'activated_at',
        'expired_at',
    ];

    protected $casts = [
        'trial_starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'subscription_starts_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
        'activated_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isOwnedBy(User $user)
    {
        return (int) $this->owner_user_id === (int) $user->id;
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

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function kasirRequestLogs()
    {
        return $this->hasMany(KasirRequestLog::class);
    }

    public function clientRequestLogs()
    {
        return $this->hasMany(ClientRequestLog::class);
    }
}
