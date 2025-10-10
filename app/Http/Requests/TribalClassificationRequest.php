<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TribalClassificationRequest extends FormRequest
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
        return [
            'tribe' => 'required|in:ivalino,iranmeilek,imowrod,iratay,yayo,iraraley',
            'food_category' => 'nullable|in:oyod,rahet,不分類,不食用,?,',
            'processing_method' => 'nullable|in:去魚鱗,不去魚鱗,剝皮,不食用,?,',
            'notes' => 'nullable|string|max:65535'
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tribe.required' => '請選擇部落',
            'tribe.in' => '請選擇有效的部落',
            'food_category.in' => '請選擇有效的飲食分類',
            'processing_method.in' => '請選擇有效的處理方式',
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
            'tribe' => '部落',
            'food_category' => '飲食分類',
            'processing_method' => '處理方式',
            'notes' => '調查備註'
        ];
    }
}
