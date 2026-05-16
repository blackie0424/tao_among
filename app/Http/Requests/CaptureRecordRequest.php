<?php

namespace App\Http\Requests;

use App\Services\CaptureRecordFieldValidator;
use Illuminate\Foundation\Http\FormRequest;

class CaptureRecordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return app(CaptureRecordFieldValidator::class)->rules($this->isMethod('POST'));
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return app(CaptureRecordFieldValidator::class)->messages();
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return app(CaptureRecordFieldValidator::class)->attributes();
    }
}
