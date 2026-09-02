<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SignedUploadUrlRequest extends FormRequest
{
    public function rules()
    {
        return [
            'filename' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpeg', 'png', 'jpg', 'gif'])) {
                        $fail('檔名格式不正確。');
                    }
                }
            ],
            'folder' => [
                'nullable',
                'string',
                'in:images,intro-slides',
            ],
        ];
    }

    public function messages()
    {
        return [
            'filename.required' => '請提供檔案名稱。',
            'filename.string' => '檔案名稱必須是字串。',
            'folder.in' => '資料夾參數不正確。',
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
