<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReferenceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'author' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:enabled,disabled'],
        ];
    }
}

