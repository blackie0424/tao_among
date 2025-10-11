<?php

namespace App\Http\Requests;

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
        $rules = [
            'tribe' => 'required|in:ivalino,iranmeilek,imowrod,iratay,yayo,iraraley',
            'location' => 'required|string|max:255',
            'capture_method' => 'required|string|max:255',
            'capture_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:65535'
        ];

        // 新增時圖片檔名必填，編輯時可選
        if ($this->isMethod('POST')) {
            $rules['image_filename'] = 'required|string'; // 前端上傳後的檔案名稱
        } else {
            $rules['image_filename'] = 'nullable|string'; // 編輯時可選
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image_filename.required' => '請上傳捕獲照片',
            'image_filename.string' => '圖片檔案名稱格式錯誤',
            'tribe.required' => '請選擇捕獲部落',
            'tribe.in' => '請選擇有效的部落',
            'location.required' => '請輸入捕獲地點',
            'location.max' => '捕獲地點不能超過 255 個字元',
            'capture_method.required' => '請輸入捕獲方式',
            'capture_method.max' => '捕獲方式不能超過 255 個字元',
            'capture_date.required' => '請選擇捕獲日期',
            'capture_date.date' => '請輸入有效的日期格式',
            'capture_date.before_or_equal' => '捕獲日期不能是未來日期',
            'notes.string' => '備註必須是文字格式',
            'notes.max' => '備註內容過長，請縮短至65535字元以內'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'image_filename' => '捕獲照片',
            'tribe' => '捕獲部落',
            'location' => '捕獲地點',
            'capture_method' => '捕獲方式',
            'capture_date' => '捕獲日期',
            'notes' => '備註'
        ];
    }
}
