<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ClientRequestLog;
use App\Models\PaymentTransaction;
use App\Services\CompanyProvisioningService;
use App\Services\KlikQrisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    use ApiResponse;

    protected $klikQrisService;

    protected $companyProvisioningService;

    public function __construct(
        KlikQrisService $klikQrisService,
        CompanyProvisioningService $companyProvisioningService
    ) {
        $this->klikQrisService = $klikQrisService;
        $this->companyProvisioningService = $companyProvisioningService;
    }

    public function checkout(Request $request)
    {
        $planCodes = array_column($this->companyProvisioningService->getAvailablePlans(), 'code');
        $validated = $request->validate([
            'plan_code' => ['required', 'string', Rule::in($planCodes)],
            'keterangan' => 'nullable|string|max:255',
        ]);

        $user = $request->user()->load('company');
        $company = $user->company;

        if (! $company) {
            return $this->errorResponse('User belum terhubung ke company manapun', 403);
        }

        $plan = $this->companyProvisioningService->findPlanByCode($validated['plan_code']);
        if (! $plan) {
            return $this->errorResponse('Paket subscription tidak ditemukan', 422);
        }

        $orderId = $this->generateOrderId($company->id);
        $payload = [
            'order_id' => $orderId,
            'id_merchant' => config('services.klikqris.merchant_id'),
            'amount' => (int) $plan['price'],
            'keterangan' => $validated['keterangan'] ?: 'Upgrade '.$plan['name'].' - '.$company->company_name,
        ];

        try {
            $gatewayResponse = $this->klikQrisService->createTransaction($payload, [
                'company_id' => $company->id,
                'request_user' => $user->id,
            ]);
        } catch (\Throwable $throwable) {
            return $this->errorResponse('Gagal menghubungi gateway pembayaran', 500, [
                'error' => $throwable->getMessage(),
            ]);
        }

        if (! $gatewayResponse['success'] || ! data_get($gatewayResponse, 'body.status')) {
            return $this->errorResponse('Gagal membuat transaksi pembayaran', 422, [
                'gateway_response' => $gatewayResponse['body'],
            ]);
        }

        $gatewayData = $this->klikQrisService->extractTransactionData((array) $gatewayResponse['body']);
        $internalStatus = $this->klikQrisService->normalizeExternalStatus(data_get($gatewayData, 'status'));
        $paymentUrl = data_get($gatewayData, 'direct_url')
            ?: data_get($gatewayData, 'redirect_url');

        $paymentTransaction = PaymentTransaction::updateOrCreate(
            ['invoice_no' => $orderId],
            [
                'company_id' => $company->id,
                'user_id' => $user->id,
                'amount' => (float) data_get($gatewayData, 'total_amount', $plan['price']),
                'currency' => 'IDR',
                'status' => $internalStatus,
                'payment_gateway' => 'klikqris',
                'payment_channel' => 'qris',
                'gateway_reference' => data_get($gatewayData, 'signature'),
                'payment_url' => $paymentUrl,
                'expires_at' => data_get($gatewayData, 'expired_at'),
                'notes' => $payload['keterangan'],
                'metadata' => [
                    'klikqris' => $gatewayData,
                    'requested_amount' => (int) $plan['price'],
                    'plan_code' => $plan['code'],
                    'plan_name' => $plan['name'],
                    'duration_days' => $plan['duration_days'],
                    'is_lifetime' => (bool) $plan['is_lifetime'],
                    'gateway_signature' => data_get($gatewayData, 'signature'),
                ],
            ]
        );

        return $this->successResponse([
            'transaction' => $paymentTransaction->fresh(),
            'plan' => $plan,
            'gateway' => [
                'order_id' => data_get($gatewayData, 'order_id'),
                'status' => data_get($gatewayData, 'status'),
                'qris_url' => data_get($gatewayData, 'qris_url'),
                'qris_image' => data_get($gatewayData, 'qris_image'),
                'direct_url' => data_get($gatewayData, 'direct_url'),
                'redirect_url' => data_get($gatewayData, 'redirect_url'),
                'total_amount' => data_get($gatewayData, 'total_amount'),
                'expired_at' => data_get($gatewayData, 'expired_at'),
                'signature' => data_get($gatewayData, 'signature'),
            ],
        ], 'Transaksi pembayaran berhasil dibuat', 201);
    }

    public function plans()
    {
        return $this->successResponse([
            'trial_days' => (int) config('subscription.trial_days', 14),
            'plans' => $this->companyProvisioningService->getAvailablePlans(),
        ], 'Daftar paket subscription berhasil diambil');
    }

    public function webhookKlikQris(Request $request)
    {
        $requestTime = now();
        $payload = $request->all();
        $gatewayData = $this->klikQrisService->extractTransactionData($payload);
        $orderId = data_get($gatewayData, 'order_id');

        $paymentTransaction = PaymentTransaction::where('invoice_no', $orderId)->first();
        $signatureValid = $this->klikQrisService->isValidWebhookSignature($payload, $paymentTransaction);

        $log = ClientRequestLog::create([
            'company_id' => optional($paymentTransaction)->company_id,
            'transaction_id' => $orderId,
            'provider' => 'klikqris',
            'event_type' => 'payment_notification',
            'request_url' => $request->fullUrl(),
            'request_method' => $request->method(),
            'request_headers' => json_encode($request->headers->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'request_body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'signature' => $this->klikQrisService->extractWebhookSignature($payload),
            'signature_valid' => $signatureValid ? 1 : 0,
            'request_time' => $requestTime,
        ]);

        if (! $paymentTransaction) {
            $responseBody = ['success' => false, 'message' => 'Transaksi tidak ditemukan'];
            $log->update([
                'response_status_code' => 404,
                'response_body' => json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_success' => 0,
                'error_message' => 'Transaksi tidak ditemukan',
                'response_time' => now(),
            ]);

            return response()->json($responseBody, 404);
        }

        if (! $signatureValid) {
            $responseBody = ['success' => false, 'message' => 'Signature tidak valid'];
            $log->update([
                'company_id' => $paymentTransaction->company_id,
                'response_status_code' => 422,
                'response_body' => json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_success' => 0,
                'error_message' => 'Signature tidak valid',
                'response_time' => now(),
            ]);

            return response()->json($responseBody, 422);
        }

        $internalStatus = $this->klikQrisService->normalizeExternalStatus(data_get($gatewayData, 'status'));
        $wasAlreadyPaid = ! empty($paymentTransaction->paid_at) || $paymentTransaction->status === 'paid';
        $metadata = (array) $paymentTransaction->metadata;
        $klikQrisMetadata = isset($metadata['klikqris']) && is_array($metadata['klikqris'])
            ? $metadata['klikqris']
            : [];
        $metadata['klikqris'] = array_merge($klikQrisMetadata, [
            'webhook_payload' => $payload,
            'latest_webhook_data' => $gatewayData,
            'latest_webhook_status' => data_get($gatewayData, 'status'),
        ]);

        DB::transaction(function () use ($paymentTransaction, $gatewayData, $internalStatus, $metadata) {
            $paymentTransaction->update([
                'status' => $internalStatus,
                'amount' => (float) (
                    data_get($gatewayData, 'amount_paid')
                    ?: data_get($gatewayData, 'total_amount')
                    ?: $paymentTransaction->amount
                ),
                'paid_at' => data_get($gatewayData, 'payment_date'),
                'metadata' => $metadata,
            ]);
        });

        if ($internalStatus === 'paid' && ! $wasAlreadyPaid) {
            $plan = $this->resolvePlanFromMetadata($metadata);
            $this->companyProvisioningService->activateCompanySubscriptionFromPayment(
                $paymentTransaction->fresh('company'),
                $plan
            );
        }

        $responseBody = ['success' => true, 'message' => 'Webhook processed'];
        $log->update([
            'company_id' => $paymentTransaction->company_id,
            'response_status_code' => 200,
            'response_body' => json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'processed_at' => now(),
            'is_success' => 1,
            'response_time' => now(),
        ]);

        return response()->json($responseBody, 200);
    }

    public function checkTransactionStatus(Request $request, $orderId)
    {
        $user = $request->user()->load('company');
        $paymentTransaction = PaymentTransaction::where('invoice_no', $orderId)
            ->where('company_id', optional($user->company)->id)
            ->first();

        if (! $paymentTransaction) {
            return $this->errorResponse('Transaksi pembayaran tidak ditemukan', 404);
        }

        try {
            $gatewayResponse = $this->klikQrisService->checkStatus($orderId, [
                'company_id' => $paymentTransaction->company_id,
                'request_user' => $user->id,
            ]);
        } catch (\Throwable $throwable) {
            return $this->errorResponse('Gagal mengecek status transaksi ke gateway', 500, [
                'error' => $throwable->getMessage(),
            ]);
        }

        if (! $gatewayResponse['success'] || ! data_get($gatewayResponse, 'body.status')) {
            return $this->errorResponse('Gagal mengambil status transaksi', 422, [
                'gateway_response' => $gatewayResponse['body'],
            ]);
        }

        $gatewayData = $this->klikQrisService->extractTransactionData((array) $gatewayResponse['body']);
        $internalStatus = $this->klikQrisService->normalizeExternalStatus(data_get($gatewayData, 'status'));
        $wasAlreadyPaid = ! empty($paymentTransaction->paid_at) || $paymentTransaction->status === 'paid';
        $metadata = (array) $paymentTransaction->metadata;
        $klikQrisMetadata = isset($metadata['klikqris']) && is_array($metadata['klikqris'])
            ? $metadata['klikqris']
            : [];
        $metadata['klikqris'] = array_merge($klikQrisMetadata, [
            'status_check_payload' => $gatewayData,
        ]);

        $paymentTransaction->update([
            'status' => $internalStatus,
            'paid_at' => data_get($gatewayData, 'paid_at'),
            'expires_at' => data_get($gatewayData, 'expired_at'),
            'gateway_reference' => data_get($gatewayData, 'signature', $paymentTransaction->gateway_reference),
            'metadata' => $metadata,
        ]);

        if ($internalStatus === 'paid' && ! $wasAlreadyPaid) {
            $plan = $this->resolvePlanFromMetadata($metadata);
            $this->companyProvisioningService->activateCompanySubscriptionFromPayment(
                $paymentTransaction->fresh('company'),
                $plan
            );
        }

        return $this->successResponse([
            'transaction' => $paymentTransaction->fresh(),
            'gateway' => $gatewayData,
        ], 'Status transaksi berhasil diambil');
    }

    protected function generateOrderId($companyId)
    {
        return 'SUB-'.$companyId.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));
    }

    protected function resolvePlanFromMetadata(array $metadata)
    {
        $planCode = data_get($metadata, 'plan_code');
        $plan = $planCode ? $this->companyProvisioningService->findPlanByCode($planCode) : null;

        if ($plan) {
            return $plan;
        }

        return [
            'code' => 'legacy_custom',
            'name' => 'Legacy Custom',
            'price' => (int) data_get($metadata, 'requested_amount', 0),
            'duration_days' => (int) data_get($metadata, 'duration_days', 30),
            'is_lifetime' => (bool) data_get($metadata, 'is_lifetime', false),
            'description' => null,
        ];
    }
}
