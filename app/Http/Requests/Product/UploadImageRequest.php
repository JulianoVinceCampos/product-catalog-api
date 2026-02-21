<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UploadImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.required' => 'An image file is required.',
            'image.image'    => 'The file must be an image.',
            'image.mimes'    => 'Accepted formats: jpeg, png, jpg, gif, webp.',
            'image.max'      => 'Image may not be larger than 5MB.',
        ];
    }
}
