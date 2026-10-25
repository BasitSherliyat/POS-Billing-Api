<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            User::query()->select(['id', 'user_name', 'email', 'user_type', 'created_at', 'updated_at'])->orderBy('id')->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'user_type' => ['required', 'string', Rule::in(['admin', 'staff', 'super admin'])],
        ]);

        $user = User::create($data);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'user_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:255'],
            'user_type' => ['sometimes', 'required', 'string', Rule::in(['admin', 'staff', 'super admin'])],
        ]);

        $user->update($data);

        return response()->json($user->refresh());
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->salesInvoices()->exists()) {
            return response()->json(['message' => 'Users with sales invoices cannot be deleted.'], 409);
        }

        $user->delete();

        return response()->json(null, 204);
    }
}
