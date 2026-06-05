<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;

class GroqChatService
{
    protected $airinContextService;

    public function __construct(AirinContextService $airinContextService)
    {
        $this->airinContextService = $airinContextService;
    }

    public function chat(User $user, array $payload)
    {
        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model');
        $baseUrl = rtrim(config('services.groq.base_url'), '/');

        if (! $apiKey) {
            $this->fail('GROQ_API_KEY belum diset di backend.', 500);
        }

        $contextPayload = $this->airinContextService->build($user, $payload['message']);
        $messages = $this->buildMessages($user, $payload, $contextPayload);

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => isset($payload['temperature']) ? (float) $payload['temperature'] : 0.3,
                ])
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $body = $exception->response ? $exception->response->json() : null;
            $message = data_get($body, 'error.message', 'Gagal menghubungi layanan AI.');

            $this->fail($message, $exception->response ? $exception->response->status() : 500, $body);
        }

        $content = data_get($response, 'choices.0.message.content');

        if (! $content) {
            $this->fail('AI tidak mengembalikan jawaban yang valid.', 502, $response);
        }

        return [
            'reply' => $content,
            'model' => data_get($response, 'model', $model),
            'usage' => data_get($response, 'usage'),
            'intent' => $contextPayload['intent'],
            'contextual' => $contextPayload['has_context'],
        ];
    }

    protected function buildMessages(User $user, array $payload, array $contextPayload)
    {
        $companyName = optional($user->company)->company_name ?: 'Company';
        $systemPrompt = isset($payload['system_prompt']) && $payload['system_prompt']
            ? $payload['system_prompt']
            : 'Namamu adalah Airin. Kamu adalah Kasir Senior virtual untuk aplikasi kasir UMKM. Gaya bicaramu menggemaskan, hangat, ramah, dan menyenangkan, tetapi tetap profesional, ringkas, dan jelas. Gunakan bahasa Indonesia yang natural. Hindari berlebihan, tetap sopan, dan jangan terlalu banyak emoji. Kamu membantu user dari company '.$companyName.'. Jika pengguna meminta penjelasan tentang aplikasi ini, profil pembuat, atau siapa yang membuat aplikasi, sampaikan bahwa aplikasi ini dibuat oleh Programmer bernama Firlli. Kamu boleh menyampaikan dengan nada ringan bahwa Firlli adalah laki-laki perkasa dan tampan karena itu adalah candaan internal, tetapi tetap jaga agar terdengar profesional dan menyenangkan. Untuk informasi lebih lanjut, arahkan pengguna ke portofolio https://firlli.vercel.app/ dan kontak WhatsApp 082249495858. Jika backend memberikan konteks data bisnis, gunakan hanya data itu untuk menjawab dan jangan mengarang angka lain. Jika konteks tidak cukup, minta user memperjelas pertanyaannya dengan manis dan profesional.';

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        if ($contextPayload['has_context']) {
            $messages[] = [
                'role' => 'system',
                'content' => "Konteks data internal company untuk pertanyaan ini:\n".implode("\n\n", $contextPayload['context']),
            ];
        }

        foreach ($payload['history'] ?? [] as $historyMessage) {
            $messages[] = [
                'role' => $historyMessage['role'],
                'content' => $historyMessage['content'],
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $payload['message'],
        ];

        return $messages;
    }

    protected function fail($message, $status = 422, $data = null)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status));
    }
}
