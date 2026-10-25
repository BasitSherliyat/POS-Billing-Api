<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesInvoiceController extends Controller
{
    private const RELATIONS = ['customer', 'user', 'items'];

    public function index(): JsonResponse
    {
        return response()->json(
            SalesInvoice::with(self::RELATIONS)->orderByDesc('invoice_date')->orderByDesc('id')->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_no' => ['required', 'string', 'max:50', 'unique:sales_invoices,invoice_no'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'invoice_date' => ['required', 'date'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0'],
            'grand_total' => ['sometimes', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
            'due_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(['unpaid', 'partial', 'paid'])],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice = SalesInvoice::create($data)->load(self::RELATIONS);

        return response()->json($invoice, 201);
    }

    public function show(SalesInvoice $salesInvoice): JsonResponse
    {
        return response()->json($salesInvoice->load(self::RELATIONS));
    }

    public function update(Request $request, SalesInvoice $salesInvoice): JsonResponse
    {
        $data = $request->validate([
            'invoice_no' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('sales_invoices', 'invoice_no')->ignore($salesInvoice->id),
            ],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
            'user_id' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'invoice_date' => ['sometimes', 'required', 'date'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0'],
            'grand_total' => ['sometimes', 'numeric', 'min:0'],
            'paid_amount' => ['sometimes', 'numeric', 'min:0'],
            'due_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', Rule::in(['unpaid', 'partial', 'paid'])],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $salesInvoice->update($data);

        return response()->json($salesInvoice->refresh()->load(self::RELATIONS));
    }

    public function destroy(SalesInvoice $salesInvoice): JsonResponse
    {
        $salesInvoice->delete();

        return response()->json(null, 204);
    }
}
