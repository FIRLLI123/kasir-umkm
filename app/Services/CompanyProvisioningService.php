<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use App\Models\CustomerGroup;
use App\Models\PaymentTransaction;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyProvisioningService
{
    public function getAvailablePlans()
    {
        return collect(config('subscription.plans', []))
            ->map(function ($plan) {
                return [
                    'code' => $plan['code'],
                    'name' => $plan['name'],
                    'price' => (int) $plan['price'],
                    'duration_days' => $plan['duration_days'],
                    'is_lifetime' => (bool) $plan['is_lifetime'],
                    'description' => $plan['description'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    public function findPlanByCode($planCode)
    {
        foreach ($this->getAvailablePlans() as $plan) {
            if ($plan['code'] === $planCode) {
                return $plan;
            }
        }

        return null;
    }

    public function createTrialCompanyWithOwner(array $attributes, $trialDays = 14)
    {
        return DB::transaction(function () use ($attributes, $trialDays) {
            $trialStartsAt = now();
            $trialEndsAt = $trialStartsAt->copy()->addDays((int) $trialDays);

            $company = Company::create([
                'company_name' => $attributes['company_name'],
                'company_code' => $this->generateUniqueCompanyCode($attributes['company_name']),
                'address' => $attributes['address'] ?? null,
                'phone' => $attributes['company_phone'] ?? $attributes['phone'] ?? null,
                'email' => $attributes['company_email'] ?? $attributes['email'],
                'logo' => null,
                'status' => 1,
                'subscription_status' => 'trial',
                'trial_starts_at' => $trialStartsAt,
                'trial_ends_at' => $trialEndsAt,
                'subscription_starts_at' => null,
                'subscription_ends_at' => null,
                'activated_at' => null,
                'expired_at' => null,
            ]);

            $user = User::create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'password' => Hash::make($attributes['password']),
                'company_id' => $company->id,
                'role' => 'OWNER',
                'phone' => $attributes['phone'] ?? null,
                'status' => '00',
            ]);

            $company->update([
                'owner_user_id' => $user->id,
            ]);

            $this->seedDefaultCompanyData($company);

            return [
                'company' => $company->fresh(),
                'user' => $user->fresh(),
            ];
        });
    }

    public function seedDefaultCompanyData(Company $company)
    {
        $groups = [
            ['group_code' => 'USER', 'group_name' => 'USER'],
            ['group_code' => 'FREELANCER', 'group_name' => 'FREELANCER'],
            ['group_code' => 'GROSIR', 'group_name' => 'GROSIR'],
        ];

        foreach ($groups as $group) {
            CustomerGroup::create([
                'company_id' => $company->id,
                'group_code' => $group['group_code'],
                'group_name' => $group['group_name'],
                'status' => '00',
            ]);
        }

        $methods = [
            ['method_code' => 'CASH', 'method_name' => 'CASH'],
            ['method_code' => 'TRANSFER', 'method_name' => 'TRANSFER'],
            ['method_code' => 'QRIS', 'method_name' => 'QRIS'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::create([
                'company_id' => $company->id,
                'method_code' => $method['method_code'],
                'method_name' => $method['method_name'],
                'status' => '00',
            ]);
        }

        $settings = [
            'store_name' => $company->company_name,
            'store_address' => $company->address,
            'store_phone' => $company->phone,
            'receipt_footer' => 'Terima kasih sudah berbelanja',
        ];

        foreach ($settings as $key => $value) {
            AppSetting::create([
                'company_id' => $company->id,
                'setting_key' => $key,
                'setting_value' => $value,
                'status' => '00',
            ]);
        }
    }

    public function buildSubscriptionSnapshot(Company $company)
    {
        $company = $this->refreshSubscriptionStatus($company);
        $endsAt = $this->resolveSubscriptionEndAt($company);
        $daysRemaining = $endsAt ? now()->diffInDays($endsAt, false) : null;

        return [
            'status' => $company->subscription_status,
            'is_active' => in_array($company->subscription_status, ['trial', 'active']),
            'is_lifetime' => $company->subscription_status === 'active' && ! $company->subscription_ends_at,
            'trial_starts_at' => optional($company->trial_starts_at)->toDateTimeString(),
            'trial_ends_at' => optional($company->trial_ends_at)->toDateTimeString(),
            'subscription_starts_at' => optional($company->subscription_starts_at)->toDateTimeString(),
            'subscription_ends_at' => optional($company->subscription_ends_at)->toDateTimeString(),
            'ends_at' => optional($endsAt)->toDateTimeString(),
            'days_remaining' => $daysRemaining,
            'show_expiry_alert' => $daysRemaining !== null && $daysRemaining >= 0 && $daysRemaining <= 2,
            'message' => $this->buildSubscriptionMessage($company->subscription_status, $daysRemaining),
        ];
    }

    public function refreshSubscriptionStatus(Company $company)
    {
        $updates = [];
        $now = now();

        if ($company->subscription_status === 'trial' && $company->trial_ends_at && $company->trial_ends_at->lt($now)) {
            $updates['subscription_status'] = 'expired';
            $updates['expired_at'] = $company->expired_at ?: $now;
        }

        if ($company->subscription_status === 'active' && $company->subscription_ends_at && $company->subscription_ends_at->lt($now)) {
            $updates['subscription_status'] = 'expired';
            $updates['expired_at'] = $company->expired_at ?: $now;
        }

        if (! empty($updates)) {
            $company->update($updates);
            $company->refresh();
        }

        return $company;
    }

    public function activateCompanySubscriptionFromPayment(PaymentTransaction $paymentTransaction, array $plan)
    {
        $company = $paymentTransaction->company()->firstOrFail();
        $now = now();
        $subscriptionStartAt = $company->subscription_starts_at ?: $now;

        if (! empty($plan['is_lifetime'])) {
            $company->update([
                'subscription_status' => 'active',
                'subscription_starts_at' => $subscriptionStartAt,
                'subscription_ends_at' => null,
                'activated_at' => $company->activated_at ?: $now,
                'expired_at' => null,
            ]);

            return $company->fresh();
        }

        $durationDays = max(1, (int) ($plan['duration_days'] ?? 0));
        $trialEndAt = $company->trial_ends_at && $company->trial_ends_at->gt($now)
            ? $company->trial_ends_at
            : null;
        $activeEndAt = $company->subscription_ends_at && $company->subscription_ends_at->gt($now)
            ? $company->subscription_ends_at
            : null;
        $baseStartAt = $activeEndAt ?: $trialEndAt ?: $now;
        $subscriptionEndAt = $baseStartAt->copy()->addDays($durationDays);

        $company->update([
            'subscription_status' => 'active',
            'subscription_starts_at' => $subscriptionStartAt,
            'subscription_ends_at' => $subscriptionEndAt,
            'activated_at' => $company->activated_at ?: $now,
            'expired_at' => null,
        ]);

        return $company->fresh();
    }

    protected function resolveSubscriptionEndAt(Company $company)
    {
        if ($company->subscription_status === 'trial') {
            return $company->trial_ends_at;
        }

        if ($company->subscription_status === 'active') {
            return $company->subscription_ends_at;
        }

        return $company->expired_at ?: $company->subscription_ends_at ?: $company->trial_ends_at;
    }

    protected function buildSubscriptionMessage($status, $daysRemaining)
    {
        if ($status === 'active' && $daysRemaining === null) {
            return 'Langganan aktif seumur hidup.';
        }

        if ($status === 'trial' && $daysRemaining !== null) {
            if ($daysRemaining < 0) {
                return 'Masa trial sudah berakhir.';
            }

            if ($daysRemaining <= 2) {
                return 'Masa trial akan segera berakhir.';
            }

            return 'Masa trial masih aktif.';
        }

        if ($status === 'active') {
            return 'Langganan aktif.';
        }

        return 'Langganan tidak aktif.';
    }

    protected function generateUniqueCompanyCode($companyName)
    {
        $baseCode = Str::upper(Str::limit($companyName, 24, ''));
        $baseCode = preg_replace('/[^A-Z0-9]/', '', $baseCode);
        $baseCode = $baseCode ?: 'COMPANY';
        $baseCode = Str::limit($baseCode, 12, '');
        $candidate = $baseCode;
        $suffix = 1;

        while (Company::where('company_code', $candidate)->exists()) {
            $candidate = Str::limit($baseCode, 9, '').str_pad((string) $suffix, 3, '0', STR_PAD_LEFT);
            $suffix++;
        }

        return $candidate;
    }
}
