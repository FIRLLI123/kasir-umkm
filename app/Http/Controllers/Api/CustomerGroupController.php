<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomerGroup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerGroupController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = CustomerGroup::query()->orderBy('group_name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->successResponse($query->get(), 'Daftar customer group berhasil diambil');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customer_groups', 'group_code')->where(function ($query) use ($request) {
                    return $query->where('company_id', $request->user()->company_id);
                }),
            ],
            'group_name' => 'required|string|max:255',
            'status' => 'nullable|in:00,99',
        ]);

        $customerGroup = CustomerGroup::create([
            'group_code' => strtoupper($validated['group_code']),
            'group_name' => $validated['group_name'],
            'status' => $validated['status'] ?? '00',
        ]);

        return $this->successResponse($customerGroup, 'Customer group berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $customerGroup = CustomerGroup::findOrFail($id);

        $validated = $request->validate([
            'group_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('customer_groups', 'group_code')
                    ->ignore($customerGroup->id)
                    ->where(function ($query) use ($request) {
                        return $query->where('company_id', $request->user()->company_id);
                    }),
            ],
            'group_name' => 'required|string|max:255',
            'status' => 'nullable|in:00,99',
        ]);

        $customerGroup->update([
            'group_code' => strtoupper($validated['group_code']),
            'group_name' => $validated['group_name'],
            'status' => $validated['status'] ?? $customerGroup->status,
        ]);

        return $this->successResponse($customerGroup->fresh(), 'Customer group berhasil diupdate');
    }

    public function destroy($id)
    {
        $customerGroup = CustomerGroup::findOrFail($id);
        $customerGroup->update(['status' => '99']);

        return $this->successResponse($customerGroup->fresh(), 'Customer group berhasil dinonaktifkan');
    }
}
