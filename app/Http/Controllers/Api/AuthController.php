<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function signup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'user_type' => ['prohibited'],
        ]);

        $data['user_type'] = 'staff';
        $user = User::create($data);
        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') === null ? null : (int) config('jwt.ttl') * 60,
            'user' => $user->only(['id', 'user_name', 'email', 'user_type']),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! $this->passwordMatches($user, $credentials['password'])) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        $token = auth('api')->login($user);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') === null ? null : (int) config('jwt.ttl') * 60,
            'user' => $user->only(['id', 'user_name', 'email', 'user_type']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out.']);
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();
        app('tymon.jwt')->unsetToken();

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') === null ? null : (int) config('jwt.ttl') * 60,
        ]);
    }

    private function passwordMatches(User $user, string $password): bool
    {
        $storedPassword = $user->getAuthPassword();
        $passwordInfo = password_get_info($storedPassword);

        if ($passwordInfo['algoName'] !== 'unknown') {
            return Hash::check($password, $storedPassword);
        }

        if (! hash_equals($storedPassword, $password)) {
            return false;
        }

        $user->password = $password;
        $user->save();

        return true;
    }
}
