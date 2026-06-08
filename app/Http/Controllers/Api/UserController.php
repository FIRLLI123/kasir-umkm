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
        $actor = auth()->user()->load('company');
        $this->ensureCanManageUsers($actor);

        $query = User::with('company')->orderBy('name');

        if (! $actor->isSuperAdmin()) {
            $query->where('company_id', $actor->company_id);
        } else {
            $query->withoutGlobalScope('company');
        }

        return $this->successResponse(
            $query->get(),
            'Daftar user berhasil diambil'
        );
    }

    public function store(Request $request)
    {
        $actor = $request->user()->load('company');
        $this->ensureCanManageUsers($actor);

        $roleOptions = $actor->isSuperAdmin()
            ? 'SUPER_ADMIN,ADMIN,KASIR'
            : 'ADMIN,KASIR';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:6',
            'company_id' => $actor->isSuperAdmin() ? 'required|exists:companies,id' : 'nullable',
            'role' => 'required|string|in:'.$roleOptions,
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:10',
        ]);

        $companyId = $actor->isSuperAdmin()
            ? $validated['company_id']
            : $actor->company_id;

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $companyId,
            'role' => strtoupper($validated['role']),
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'] ?? '00',
        ]);

        return $this->successResponse($user->load('company'), 'User berhasil dibuat', 201);
    }

    public function update(Request $request, $id)
    {
        $actor = $request->user()->load('company');
        $this->ensureCanManageUsers($actor);

        $user = $this->findManagedUser($actor, $id);

        if (! $actor->isSuperAdmin() && $actor->company && $actor->company->owner_user_id == $user->id) {
            abort(403, 'Owner company tidak bisa diubah melalui menu karyawan.');
        }

        $roleOptions = $actor->isSuperAdmin()
            ? 'SUPER_ADMIN,ADMIN,KASIR'
            : 'ADMIN,KASIR';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6',
            'company_id' => $actor->isSuperAdmin() ? 'required|exists:companies,id' : 'nullable',
            'role' => 'required|string|in:'.$roleOptions,
            'phone' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:10',
        ]);

        $companyId = $actor->isSuperAdmin()
            ? $validated['company_id']
            : $user->company_id;

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_id' => $companyId,
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
        $actor = auth()->user()->load('company');
        $this->ensureCanManageUsers($actor);

        $user = $this->findManagedUser($actor, $id);

        if ($actor->id == $user->id) {
            abort(403, 'User login saat ini tidak bisa dinonaktifkan dari menu ini.');
        }

        if (! $actor->isSuperAdmin() && $actor->company && $actor->company->owner_user_id == $user->id) {
            abort(403, 'Owner company tidak bisa dinonaktifkan melalui menu karyawan.');
        }

        $user->update(['status' => '99']);

        return $this->successResponse($user->fresh()->load('company'), 'User berhasil dinonaktifkan');
    }

    protected function ensureCanManageUsers(User $actor)
    {
        abort_unless($actor && $actor->company_id, 403, 'User belum terhubung ke company manapun.');

        if ($actor->isSuperAdmin()) {
            return;
        }

        abort_unless(
            $actor->company && $actor->company->isOwnedBy($actor),
            403,
            'Akses hanya untuk owner company.'
        );
    }

    protected function findManagedUser(User $actor, $id)
    {
        $query = User::with('company');

        if ($actor->isSuperAdmin()) {
            return $query->withoutGlobalScope('company')->findOrFail($id);
        }

        return $query
            ->where('company_id', $actor->company_id)
            ->findOrFail($id);
    }
}
