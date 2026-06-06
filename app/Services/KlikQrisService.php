<?php

namespace App\Services;

use App\Models\KasirRequestLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class KlikQrisService
{
    public function createTransaction(array $payload, array $context = [])
    {
        $url = $this->createTransactionUrl();
        $requestTime = now();
        $log = KasirRequestLog::create([
            'company_id' => $context['company_id'] ?? null,
            'request_user' => $context['request_user'] ?? null,
            'transaction_id' => $payload['order_id'] ?? null,
            'provider' => 'klikqris',
            'action' => 'create_qris',
            'request_url' => $url,
            'request_method' => 'POST',
            'request_headers' => $this->encode($this->headers()),
            'request_body' => $this->encode($payload),
            'request_time' => $requestTime,
        ]);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($url, $payload);

            $log->update([
                'response_status_code' => $response->status(),
                'response_headers' => $this->encode($response->headers()),
                'response_body' => $response->body(),
                'is_success' => $response->successful() ? 1 : 0,
                'error_message' => $response->successful() ? null : $response->body(),
                'response_time' => now(),
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->json(),
                'raw_body' => $response->body(),
            ];
        } catch (\Throwable $throwable) {
            $log->update([
                'is_success' => 0,
                'error_message' => $throwable->getMessage(),
                'response_time' => now(),
            ]);

            throw $throwable;
        }
    }

    public function checkStatus($orderId, array $context = [])
    {
        $url = $this->statusUrl($orderId);
        $requestTime = now();
        $log = KasirRequestLog::create([
            'company_id' => $context['company_id'] ?? null,
            'request_user' => $context['request_user'] ?? null,
            'transaction_id' => $orderId,
            'provider' => 'klikqris',
            'action' => 'check_status',
            'request_url' => $url,
            'request_method' => 'GET',
            'request_headers' => $this->encode($this->headers()),
            'request_time' => $requestTime,
        ]);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->get($url);

            $log->update([
                'response_status_code' => $response->status(),
                'response_headers' => $this->encode($response->headers()),
                'response_body' => $response->body(),
                'is_success' => $response->successful() ? 1 : 0,
                'error_message' => $response->successful() ? null : $response->body(),
                'response_time' => now(),
            ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'body' => $response->json(),
                'raw_body' => $response->body(),
            ];
        } catch (\Throwable $throwable) {
            $log->update([
                'is_success' => 0,
                'error_message' => $throwable->getMessage(),
                'response_time' => now(),
            ]);

            throw $throwable;
        }
    }

    public function normalizeExternalStatus($status)
    {
        $normalized = Str::upper((string) $status);

        if (in_array($normalized, ['SUCCESS', 'PAID'])) {
            return 'paid';
        }

        if ($normalized === 'PENDING') {
            return 'pending';
        }

        if ($normalized === 'EXPIRED') {
            return 'expired';
        }

        if ($normalized === 'FAILED') {
            return 'failed';
        }

        return 'unknown';
    }

    public function shouldValidateWebhookSignature()
    {
        return (bool) config('services.klikqris.validate_webhook_signature', false);
    }

    public function isValidWebhookSignature(array $payload, $paymentTransaction = null)
    {
        if (! $this->shouldValidateWebhookSignature()) {
            return true;
        }

        $signature = $this->extractWebhookSignature($payload);
        if (! $signature) {
            return false;
        }

        $metadata = $paymentTransaction ? (array) $paymentTransaction->metadata : [];
        $storedSignature = $metadata['gateway_signature'] ?? null;

        return $storedSignature && hash_equals((string) $storedSignature, (string) $signature);
    }

    public function extractTransactionData(array $payload)
    {
        $data = data_get($payload, 'data');

        if (is_array($data) && ! empty($data)) {
            return $data;
        }

        return $payload;
    }

    public function extractWebhookSignature(array $payload)
    {
        return data_get($payload, 'data.signature') ?: data_get($payload, 'signature');
    }

    public function currentMode()
    {
        return Str::lower((string) config('services.klikqris.mode', 'sandbox'));
    }

    public function isProductionMode()
    {
        return $this->currentMode() === 'production';
    }

    protected function headers()
    {
        return [
            'Content-Type' => 'application/json',
            'x-api-key' => config('services.klikqris.api_key'),
            'id_merchant' => config('services.klikqris.merchant_id'),
        ];
    }

    protected function baseUrl()
    {
        return rtrim((string) config('services.klikqris.base_url'), '/');
    }

    protected function createTransactionUrl()
    {
        return $this->baseUrl().'/qris/create';
    }

    protected function statusUrl($orderId)
    {
        return $this->baseUrl().'/qris/status/'.rawurlencode($orderId);
    }

    protected function encode($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
