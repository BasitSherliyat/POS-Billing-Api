<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesInvoiceItemController extends Controller
{
    private const RELATIONS = ['invoice:id,invoice_no', 'item:id,item_name'];

    public function index(): JsonResponse
    {
        return response()->json(
            SalesInvoiceItem::with(self::RELATIONS)->orderBy('id')->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:sales_invoices,id'],
            'item_id' => ['nullable', 'integer', 'exists:items,id'],
            'item_name' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
        ]);

        $invoiceItem = SalesInvoiceItem::create($data)->load(self::RELATIONS);

        return response()->json($invoiceItem, 201);
    }

    public function show(SalesInvoiceItem $salesInvoiceItem): JsonResponse
    {
        return response()->json($salesInvoiceItem->load(self::RELATIONS));
    }

    public function update(Request $request, SalesInvoiceItem $salesInvoiceItem): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => ['sometimes', 'required', 'integer', 'exists:sales_invoices,id'],
            'item_id' => ['sometimes', 'nullable', 'integer', 'exists:items,id'],
            'item_name' => ['sometimes', 'required', 'string', 'max:150'],
            'unit' => ['sometimes', 'required', 'string', 'max:30'],
            'quantity' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'total' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $salesInvoiceItem->update($data);

        return response()->json($salesInvoiceItem->refresh()->load(self::RELATIONS));
    }

    public function destroy(SalesInvoiceItem $salesInvoiceItem): JsonResponse
    {
        $salesInvoiceItem->delete();

        return response()->json(null, 204);
    }
}
