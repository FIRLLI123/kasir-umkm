<?php

namespace App\Services;

use App\Models\AiChatLog;
use App\Models\User;

class AiChatAccessService
{
    public function getUsageSnapshot(User $user)
    {
        $company = $user->company;
        $subscription = $company ? app(CompanyProvisioningService::class)->buildSubscriptionSnapshot($company) : null;
        $isTrial = $subscription && $subscription['status'] === 'trial';
        $dailyLimit = $isTrial ? (int) config('subscription.ai_trial_daily_limit', 3) : null;
        $usedToday = $company ? $this->countSuccessfulChatsToday($company->id) : 0;
        $remainingToday = $dailyLimit === null ? null : max(0, $dailyLimit - $usedToday);

        return [
            'subscription_status' => $subscription ? $subscription['status'] : null,
            'is_trial' => $isTrial,
            'daily_limit' => $dailyLimit,
            'used_today' => $usedToday,
            'remaining_today' => $remainingToday,
            'can_chat' => $dailyLimit === null || $remainingToday > 0,
            'show_upgrade' => $isTrial && $remainingToday === 0,
            'message' => $this->buildUsageMessage($isTrial, $remainingToday),
        ];
    }

    public function enforceOrFail(User $user)
    {
        $snapshot = $this->getUsageSnapshot($user);

        if (! $snapshot['can_chat']) {
            $message = 'Batas harian chat AI untuk akun trial sudah habis.';

            abort(response()->json([
                'success' => false,
                'message' => $message,
                'data' => [
                    'limit_type' => 'trial_daily_ai_chat',
                    'daily_limit' => $snapshot['daily_limit'],
                    'used_today' => $snapshot['used_today'],
                    'remaining_today' => $snapshot['remaining_today'],
                    'subscription_status' => $snapshot['subscription_status'],
                    'show_upgrade' => true,
                    'upgrade_message' => 'Batas harian chat AI untuk akun trial hanya 3 kali. Yuk lakukan upgrade untuk menikmati akses yang lebih penuh.',
                ],
            ], 429));
        }

        return $snapshot;
    }

    public function logSuccess(User $user, array $payload, array $result)
    {
        AiChatLog::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'subscription_status' => optional($user->company)->subscription_status,
            'message' => $payload['message'],
            'reply' => $result['reply'] ?? null,
            'model' => $result['model'] ?? null,
            'is_success' => 1,
            'requested_at' => now(),
            'responded_at' => now(),
        ]);
    }

    protected function countSuccessfulChatsToday($companyId)
    {
        return (int) AiChatLog::query()
            ->where('company_id', $companyId)
            ->where('is_success', 1)
            ->whereDate('created_at', now()->toDateString())
            ->count();
    }

    protected function buildUsageMessage($isTrial, $remainingToday)
    {
        if (! $isTrial) {
            return 'Akses chat AI tersedia.';
        }

        if ($remainingToday <= 0) {
            return 'Batas harian chat AI untuk akun trial sudah habis.';
        }

        return 'Sisa chat AI hari ini: '.$remainingToday.'.';
    }
}
