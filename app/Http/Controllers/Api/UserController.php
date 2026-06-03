<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $this->ensureSuperAdmin();

        return $this->successResponse(
            User::withoutGlobalScope('company')->with('company')->orderBy('name')->get(),
            'Daftar user berhasil diambil'
        );
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'company_id' => 'required|exists:companies,id',
            'role' => 'required|string|in:SUPER_ADMIN,ADMIN,KASIR',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:10',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $validated['company_id'],
            'role' => strtoupper($validated['role']),
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? '00',
        ]);

        return $this->successResponse($user->load('company'), 'User berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $this->ensureSuperAdmin();

        $user = User::withoutGlobalScope('company')->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6',
            'company_id' => 'required|exists:companies,id',
            'role' => 'required|string|in:SUPER_ADMIN,ADMIN,KASIR',
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:10',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_id' => $validated['company_id'],
            'role' => strtoupper($validated['role']),
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? $user->status,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return $this->successResponse($user->fresh()->load('company'), 'User berhasil diupdate');
    }

    public function destroy($id)
    {
        $this->ensureSuperAdmin();

        $user = User::withoutGlobalScope('company')->findOrFail($id);
        
        // Soft deactivation instead of physical delete
        $user->update(['status' => '99']);

        return $this->successResponse($user->fresh()->load('company'), 'User berhasil dinonaktifkan');
    }

    protected function ensureSuperAdmin()
    {
        abort_unless(auth()->check() && auth()->user()->isSuperAdmin(), 403, 'Akses hanya untuk super admin.');
    }
}
