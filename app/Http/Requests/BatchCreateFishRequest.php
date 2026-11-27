<?php

namespace App\Http\Requests;

use App\Services\CaptureRecordFieldValidator;
use Illuminate\Foundation\Http\FormRequest;

class BatchCreateFishRequest extends FormRequest
{
    public function rules(): array
    {
        $sessionRules = app(CaptureRecordFieldValidator::class)->webSessionCreateRules(false);
        unset($sessionRules['image_filename']);

        return [...$sessionRules, ...[
            'name' => ['nullable', 'string', 'max:255'],
            'filenames' => ['required', 'array', 'min:1'],
            'filenames.*' => ['required', 'string'],
        ]];
    }

    public function messages(): array
    {
        return app(CaptureRecordFieldValidator::class)->messages();
    }
}
