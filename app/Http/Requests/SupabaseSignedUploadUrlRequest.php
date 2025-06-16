<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SupabaseSignedUploadUrlRequest extends FormRequest
{
    public function rules()
    {
        return [
            'filename' => ['required', 'string'],
            'path' => ['nullable', 'string'],
        ];
    }

    public function messages()
    {
        return [
            'filename.required' => '請提供檔案名稱。',
            'filename.string' => '檔案名稱必須是字串。',
        ];
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 400)
        );
    }
}
