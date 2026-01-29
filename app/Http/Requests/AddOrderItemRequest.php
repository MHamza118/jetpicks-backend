<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddOrderItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_name' => 'required|string|max:255',
            'weight' => 'required|string|max:50',
            'price' => 'required|numeric|min:0.01',
            'quantity' => 'sometimes|integer|min:1',
            'special_notes' => 'sometimes|string|max:1000',
            'store_link' => 'nullable|string|url',
            'product_images' => 'sometimes|array',
            'product_images.*' => 'image|max:10240', // 10MB max
        ];
    }

    public function prepareForValidation()
    {
        // Convert empty string to null for store_link
        if ($this->has('store_link') && $this->store_link === '') {
            $this->merge(['store_link' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'item_name.required' => 'Item name is required',
            'weight.required' => 'Weight is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price must be greater than 0',
            'quantity.integer' => 'Quantity must be an integer',
            'quantity.min' => 'Quantity must be at least 1',
            'store_link.url' => 'Store link must be a valid URL',
            'product_images.array' => 'Product images must be an array',
            'product_images.*.url' => 'Each product image must be a valid URL',
        ];
    }
}
