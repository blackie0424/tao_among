<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchCreateFishRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'           => ['nullable', 'string', 'max:255'],
            'filenames'      => ['required', 'array', 'min:1'],
            'filenames.*'    => ['required', 'string'],
            'tribe'          => ['nullable', 'string'],
            'location'       => ['nullable', 'string'],
            'capture_method' => ['nullable', 'string'],
            'capture_date'   => ['nullable', 'date'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
