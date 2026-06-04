<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\GroqChatService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    use ApiResponse;

    protected $groqChatService;

    public function __construct(GroqChatService $groqChatService)
    {
        $this->groqChatService = $groqChatService;
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

        $result = $this->groqChatService->chat($request->user()->load('company'), $validated);

        return $this->successResponse($result, 'Chat AI berhasil diproses');
    }
}
