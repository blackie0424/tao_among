<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferenceKnowledgeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reference_id' => [
                'required',
                Rule::exists('references', 'id')->where(fn ($query) => $query->where('status', 'enabled')),
            ],
            'tribe' => ['nullable', 'string', Rule::in(config('fish_options.tribes', []))],
            'content' => ['required', 'string'],
            'pages' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ];
    }
}
