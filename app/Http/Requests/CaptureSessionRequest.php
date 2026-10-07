<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaptureSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roleCanAccessLocation($this->user()?->role) ?? false;
    }

    public function rules(): array
    {
        return [
            'capture_date' => ['required', 'date', 'before_or_equal:today'],
            'tribe' => ['required', Rule::in(config('fish_options.tribes'))],
            'capture_method' => ['required', 'string', 'max:255'],
            'place_id' => ['nullable', 'integer', 'exists:places,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
