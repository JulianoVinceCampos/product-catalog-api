<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku'         => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name'        => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0.01'],
            'category'    => ['nullable', 'string', 'max:100'],
            'status'      => ['nullable', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required'   => 'The SKU field is required.',
            'sku.unique'     => 'This SKU is already registered.',
            'name.required'  => 'The name field is required.',
            'name.min'       => 'The name must be at least 3 characters.',
            'price.required' => 'The price field is required.',
            'price.min'      => 'The price must be greater than zero.',
        ];
    }
}
