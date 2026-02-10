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
            'weight' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0.01',
            'quantity' => 'required|integer|min:1',
            'currency' => 'nullable|string|size:3',
            'special_notes' => 'nullable|string|max:1000',
            'store_link' => 'nullable|string|max:500',
            'product_images' => 'sometimes|array',
            'product_images.*' => 'image|max:102400', // 100MB max
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
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price must be greater than 0',
            'quantity.required' => 'Quantity is required',
            'quantity.integer' => 'Quantity must be an integer',
            'quantity.min' => 'Quantity must be at least 1',
            'store_link.max' => 'Store link must not exceed 500 characters',
            'product_images.array' => 'Product images must be an array',
            'product_images.*.image' => 'Each product image must be a valid image file (JPEG, PNG, WebP, GIF, HEIC)',
            'product_images.*.max' => 'Each product image must not exceed 100MB',
        ];
    }
}
