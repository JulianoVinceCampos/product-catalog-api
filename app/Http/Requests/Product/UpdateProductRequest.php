<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id ?? $this->route('product');

        return [
            'sku'         => ["sometimes", "string", "max:100", "unique:products,sku,{$productId}"],
            'name'        => ['sometimes', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0.01'],
            'category'    => ['nullable', 'string', 'max:100'],
            'status'      => ['nullable', 'in:active,inactive'],
        ];
    }
}
