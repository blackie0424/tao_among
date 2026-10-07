<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['editor', 'admin'], true);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:191'], 'tao_name' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']];
    }
}
