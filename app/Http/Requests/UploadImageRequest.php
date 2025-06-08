<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UploadImageRequest extends FormRequest
{
    public function rules()
    {
        return [
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:4403', 'min:1'],
        ];
    }

    public function messages()
    {
        return [
            'image.image' => '只能上傳單一圖片檔案。',
            'image.required' => '請選擇要上傳的圖片。',
            'image.mimes' => '圖片格式僅限 jpeg, png, jpg, gif, svg。',
            'image.max' => '圖片大小不可超過 4403 KB。',
            'image.min' => '圖片檔案不可為空。',
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