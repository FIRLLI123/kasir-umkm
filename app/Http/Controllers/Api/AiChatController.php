<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AiChatAccessService;
use App\Services\GroqChatService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    use ApiResponse;

    protected $groqChatService;

    protected $aiChatAccessService;

    public function __construct(GroqChatService $groqChatService, AiChatAccessService $aiChatAccessService)
    {
        $this->groqChatService = $groqChatService;
        $this->aiChatAccessService = $aiChatAccessService;
    }

    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:5000',
            'system_prompt' => 'nullable|string|max:5000',
            'temperature' => 'nullable|numeric|min:0|max:2',
        ]);

        $user = $request->user()->load('company');
        $usageBefore = $this->aiChatAccessService->enforceOrFail($user);
        $result = $this->groqChatService->chat($user, $validated);
        $this->aiChatAccessService->logSuccess($user, $validated, $result);
        $usageAfter = $this->aiChatAccessService->getUsageSnapshot($user);

        $result['ai_chat_limit'] = [
            'daily_limit' => $usageAfter['daily_limit'],
            'used_today' => $usageAfter['used_today'],
            'remaining_today' => $usageAfter['remaining_today'],
            'subscription_status' => $usageAfter['subscription_status'],
            'show_upgrade' => $usageAfter['show_upgrade'],
            'message' => $usageAfter['message'],
        ];

        return $this->successResponse($result, 'Chat AI berhasil diproses');
    }
}
