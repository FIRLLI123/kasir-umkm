<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Customer::with('customerGroup')->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($builder) use ($search) {
                $builder->where('customer_name', 'like', '%'.$search.'%')
                    ->orWhere('customer_code', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_group_id')) {
            $query->where('customer_group_id', $request->customer_group_id);
        }

        $customers = $query->paginate((int) $request->get('per_page', 10));

        return $this->successResponse($customers->items(), 'Daftar customer berhasil diambil', 200, [
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ],
        ]);
    }

    public function show($id)
    {
        $customer = Customer::with('customerGroup')->findOrFail($id);

        return $this->successResponse($customer, 'Detail customer berhasil diambil');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_code' => 'nullable|string|max:50|unique:customers,customer_code',
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'customer_group_id' => 'required|exists:customer_groups,id',
            'status' => 'nullable|in:00,99',
        ]);

        $customer = Customer::create([
            'customer_code' => $validated['customer_code'] ?? null,
            'customer_name' => $validated['customer_name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'customer_group_id' => $validated['customer_group_id'],
            'status' => $validated['status'] ?? '00',
        ]);

        return $this->successResponse($customer->load('customerGroup'), 'Customer berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $validated = $request->validate([
            'customer_code' => 'nullable|string|max:50|unique:customers,customer_code,'.$customer->id,
            'customer_name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'customer_group_id' => 'required|exists:customer_groups,id',
            'status' => 'nullable|in:00,99',
        ]);

        $customer->update([
            'customer_code' => $validated['customer_code'] ?? null,
            'customer_name' => $validated['customer_name'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'customer_group_id' => $validated['customer_group_id'],
            'status' => $validated['status'] ?? $customer->status,
        ]);

        return $this->successResponse($customer->fresh()->load('customerGroup'), 'Customer berhasil diupdate');
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->update(['status' => '99']);

        return $this->successResponse($customer->fresh()->load('customerGroup'), 'Customer berhasil dinonaktifkan');
    }
}
