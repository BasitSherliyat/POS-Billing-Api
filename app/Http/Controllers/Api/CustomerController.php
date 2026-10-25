<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Customer::query()->orderBy('id')->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_name' => ['nullable', 'string', 'max:150'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_contact' => ['required', 'string', 'max:30'],
            'alt_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(Customer::create($data), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'shop_name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'customer_name' => ['sometimes', 'required', 'string', 'max:150'],
            'customer_contact' => ['sometimes', 'required', 'string', 'max:30'],
            'alt_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $customer->update($data);

        return response()->json($customer->refresh());
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(null, 204);
    }
}
