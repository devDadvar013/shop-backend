<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     * Body: { email, password, device_name? }
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => ['required', 'email', 'max:191'],
            'password'    => ['required', 'string', 'min:6', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ])->status(422);
        }

        $deviceName = $data['device_name'] ?? ($request->userAgent() ?: 'web');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Logged in.',
            'data'    => [
                'user'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * POST /api/auth/logout
     * Revokes the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * POST /api/auth/logout-all
     * Revokes every token for the user.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()?->tokens()->delete();
        return response()->json(['message' => 'All sessions revoked.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'data' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * PUT /api/auth/profile
     * Update name/email/password.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'min:2', 'max:120'],
            'email'                 => ['sometimes', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password'      => ['required_with:password', 'nullable', 'string'],
            'password'              => ['sometimes', 'nullable', 'string', 'min:6', 'max:191', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            if (empty($data['current_password']) || ! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Current password is incorrect.'],
                ])->status(422);
            }
            $user->password = $data['password'];
            unset($data['password'], $data['current_password']);
        }

        $user->fill(array_intersect_key($data, array_flip(['name', 'email'])));
        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
