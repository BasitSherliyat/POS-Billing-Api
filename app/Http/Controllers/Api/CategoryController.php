<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::query()->withCount('items')->orderBy('id')->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_name' => ['required', 'string', 'max:100'],
        ]);

        return response()->json(Category::create($data), 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json($category);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'category_name' => ['sometimes', 'required', 'string', 'max:100'],
        ]);

        $category->update($data);

        return response()->json($category->refresh());
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->items()->exists()) {
            return response()->json(['message' => 'Categories with items cannot be deleted.'], 409);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
