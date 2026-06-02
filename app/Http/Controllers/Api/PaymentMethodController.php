<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = PaymentMethod::query()->orderBy('method_name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->successResponse($query->get(), 'Daftar metode pembayaran berhasil diambil');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'method_code' => 'required|string|max:50|unique:payment_methods,method_code',
            'method_name' => 'required|string|max:255',
            'status' => 'nullable|in:00,99',
        ]);

        $paymentMethod = PaymentMethod::create([
            'method_code' => strtoupper($validated['method_code']),
            'method_name' => $validated['method_name'],
            'status' => $validated['status'] ?? '00',
        ]);

        return $this->successResponse($paymentMethod, 'Metode pembayaran berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);

        $validated = $request->validate([
            'method_code' => 'required|string|max:50|unique:payment_methods,method_code,'.$paymentMethod->id,
            'method_name' => 'required|string|max:255',
            'status' => 'nullable|in:00,99',
        ]);

        $paymentMethod->update([
            'method_code' => strtoupper($validated['method_code']),
            'method_name' => $validated['method_name'],
            'status' => $validated['status'] ?? $paymentMethod->status,
        ]);

        return $this->successResponse($paymentMethod->fresh(), 'Metode pembayaran berhasil diupdate');
    }

    public function destroy($id)
    {
        $paymentMethod = PaymentMethod::findOrFail($id);
        $paymentMethod->update(['status' => '99']);

        return $this->successResponse($paymentMethod->fresh(), 'Metode pembayaran berhasil dinonaktifkan');
    }
}
