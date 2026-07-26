<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('item');

        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('items', 'code')->ignore($itemId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('items', 'barcode')->ignore($itemId)],
            'batch' => ['nullable', 'string', 'max:100'],
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'shelf_id' => 'nullable|exists:shelves,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'unit' => ['nullable', 'string', 'max:50'],
            'sub_unit' => ['nullable', 'string', 'max:50'],
            'sub_unit_quantity' => ['nullable', 'integer', 'min:1'],
            'quantity' => 'required|integer|min:0',
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'description' => 'nullable|string',
            'expiry_date' => 'nullable|date',
            'is_active' => 'boolean',
        ];
    }
}
