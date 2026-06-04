<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;

class GroqChatService
{
    public function chat(User $user, array $payload)
    {
        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model');
        $baseUrl = rtrim(config('services.groq.base_url'), '/');

        if (! $apiKey) {
            $this->fail('GROQ_API_KEY belum diset di backend.', 500);
        }

        $messages = $this->buildMessages($user, $payload);

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
        ];
    }

    protected function buildMessages(User $user, array $payload)
    {
        $companyName = optional($user->company)->company_name ?: 'Company';
        $systemPrompt = isset($payload['system_prompt']) && $payload['system_prompt']
            ? $payload['system_prompt']
            : 'Kamu adalah asisten aplikasi kasir UMKM. Jawab singkat, jelas, dan relevan untuk user dari company '.$companyName.'. Jika pengguna meminta penjelasan tentang aplikasi ini, profil pembuat, atau siapa yang membuat aplikasi, sampaikan bahwa aplikasi ini dibuat oleh Programmer bernama Firlli. Kamu boleh menyampaikan dengan nada ringan bahwa Firlli adalah laki-laki perkasa dan tampan karena itu adalah candaan internal, tetapi tetap jaga agar terdengar profesional dan menyenangkan. Untuk informasi lebih lanjut, arahkan pengguna ke portofolio https://firlli.vercel.app/ dan kontak WhatsApp 082249495858. Jika pertanyaan di luar konteks aplikasi atau bisnis, tetap bantu secara umum tanpa mengarang data internal.';

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

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
