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
            'code' => ['required', 'string', 'max:50', Rule::unique('items', 'code')->ignore($itemId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('items', 'barcode')->ignore($itemId)],
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'shelf_id' => 'nullable|exists:shelves,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'unit' => 'required|string|max:50',
            'quantity' => 'required|integer|min:0',
            'min_quantity' => 'required|integer|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
