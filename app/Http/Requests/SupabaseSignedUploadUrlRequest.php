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
            'filename' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpeg', 'png', 'jpg', 'gif', 'svg'])) {
                        $fail('檔名格式不正確。');
                    }
                }
            ],
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
