<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_id' => 'required|string|max:255',
            'device_name' => 'required|string|max:255',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Email atau password tidak valid', 401);
        }

        if ($user->status !== '00') {
            return $this->errorResponse('User tidak aktif', 403);
        }

        $user->tokens()->delete();

        $user->forceFill([
            'device_id' => $validated['device_id'],
            'device_name' => $validated['device_name'],
            'last_login_at' => now(),
        ])->save();

        $token = $user->createToken($validated['device_name'])->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => $user,
        ], 'Login berhasil');
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->successResponse(null, 'Logout berhasil');
    }

    public function profile(Request $request)
    {
        return $this->successResponse($request->user(), 'Profile berhasil diambil');
    }
}
