<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ItemController extends Controller
{
    public function uploadImage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $directory = public_path('images/items');
        File::ensureDirectoryExists($directory);

        $filename = Str::uuid().'.'.$data['image']->extension();
        $data['image']->move($directory, $filename);

        return response()->json([
            'item_image' => '/images/items/'.$filename,
        ], 201);
    }

    public function bulkUpload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'extensions:csv', 'max:10240'],
        ]);

        $handle = fopen($data['file']->getRealPath(), 'r');
        if ($handle === false) {
            throw new RuntimeException('Unable to read uploaded CSV file.');
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');
            if ($headers === false || $headers === [null]) {
                throw ValidationException::withMessages([
                    'file' => ['The CSV file must contain a header row.'],
                ]);
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(static fn ($header) => trim((string) $header), $headers);
            $allowedHeaders = ['item_name', 'category_name', 'price', 'unit', 'barcode', 'item_image'];
            $requiredHeaders = ['item_name', 'category_name', 'unit'];
            $unknownHeaders = array_diff($headers, $allowedHeaders);
            $missingHeaders = array_diff($requiredHeaders, $headers);

            if (count($headers) !== count(array_unique($headers)) || $unknownHeaders !== [] || $missingHeaders !== []) {
                throw ValidationException::withMessages([
                    'file' => ['CSV headers must be unique and include item_name, category_name, and unit. Supported headers: item_name, category_name, price, unit, barcode, item_image.'],
                ]);
            }

            $categoriesByName = Category::query()->get(['id', 'category_name'])->groupBy('category_name');
            $createdItems = [];
            $errors = [];
            $seenBarcodes = [];
            $rowNumber = 1;
            $hasDataRows = false;

            while (($values = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                $rowNumber++;

                if ($values === [null] || count(array_filter($values, static fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $hasDataRows = true;

                if (count($values) !== count($headers)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => ['csv' => ['The number of columns does not match the header row.']],
                    ];

                    continue;
                }

                $row = array_combine($headers, array_map(
                    static fn ($value) => trim((string) $value),
                    $values,
                ));

                foreach (['price', 'barcode', 'item_image'] as $optionalField) {
                    if (($row[$optionalField] ?? '') === '') {
                        unset($row[$optionalField]);
                    }
                }

                $validator = Validator::make($row, [
                    'item_name' => ['required', 'string', 'max:150'],
                    'category_name' => ['required', 'string', 'max:100'],
                    'price' => ['sometimes', 'numeric', 'min:0'],
                    'unit' => ['required', 'string', 'max:30'],
                    'barcode' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('items', 'barcode')],
                    'item_image' => ['sometimes', 'nullable', 'string', 'max:255'],
                ]);

                $barcode = $row['barcode'] ?? '';
                $duplicateBarcodeInUpload = $barcode !== '' && isset($seenBarcodes[$barcode]);
                if ($validator->fails() || $duplicateBarcodeInUpload) {
                    $rowErrors = $validator->errors()->toArray();
                    if ($duplicateBarcodeInUpload) {
                        $rowErrors['barcode'] = ['The barcode has already been used in this CSV file.'];
                    }
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => $rowErrors,
                    ];

                    continue;
                }

                $categoryMatches = $categoriesByName->get($row['category_name'], collect());
                if ($categoryMatches->count() !== 1) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'errors' => [
                            'category_name' => [
                                $categoryMatches->isEmpty()
                                    ? 'The selected category name does not exist.'
                                    : 'The selected category name is ambiguous.',
                            ],
                        ],
                    ];

                    continue;
                }

                $itemData = $validator->validated();
                $itemData['category_id'] = $categoryMatches->first()->id;
                unset($itemData['category_name']);

                $item = Item::create($itemData);
                if ($barcode !== '') {
                    $seenBarcodes[$barcode] = true;
                }
                $createdItems[] = [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                ];
            }

            if (! $hasDataRows) {
                throw ValidationException::withMessages([
                    'file' => ['The CSV file must contain at least one data row.'],
                ]);
            }
        } finally {
            fclose($handle);
        }

        return response()->json([
            'imported_count' => count($createdItems),
            'failed_count' => count($errors),
            'items' => $createdItems,
            'errors' => $errors,
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(Item::with('category')->orderBy('id')->paginate(15));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_name' => ['required', 'string', 'max:150'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('items', 'barcode')],
            'item_image' => ['nullable', 'string', 'max:255'],
        ]);

        $item = Item::create($data)->load('category');

        return response()->json($item, 201);
    }

    public function show(Item $item): JsonResponse
    {
        return response()->json($item->load('category'));
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'item_name' => ['sometimes', 'required', 'string', 'max:150'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('items', 'barcode')->ignore($item->id)],
            'item_image' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $item->update($data);

        return response()->json($item->refresh()->load('category'));
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json(null, 204);
    }
}
