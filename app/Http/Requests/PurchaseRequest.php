<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_number' => 'required|string|max:100|unique:purchases,invoice_number,' . $this->route('purchase'),
            'supplier_id' => 'nullable|exists:suppliers,id',
            'invoice_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,pdf|max:10240',
            'total' => 'required|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'status' => 'required|in:pending,completed,cancelled',
            'purchase_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.item_name' => 'required_without:items.*.item_id|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.warehouse_id' => 'nullable|exists:warehouses,id',
            'items.*.category_id' => 'nullable|exists:categories,id',
            'items.*.unit' => 'nullable|string|max:50',
            'items.*.image' => 'nullable|file|image|max:5120',
        ];
    }
}
