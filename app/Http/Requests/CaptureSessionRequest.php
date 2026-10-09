<?php

namespace App\Http\Requests;

use App\Support\CaptureDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CaptureSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->roleCanAccessLocation($this->user()?->role) ?? false;
    }

    public function rules(): array
    {
        return [
            'capture_date' => ['required', 'date', 'before_or_equal:'.CaptureDate::today()],
            'tribe' => ['required', Rule::in(config('fish_options.tribes'))],
            'capture_method' => ['required', 'string', 'max:255'],
            'place_id' => ['nullable', 'integer', 'exists:places,id'],
            'place_name' => ['nullable', 'string', 'max:191', 'prohibits:place_id'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'capture_date.required' => '請選擇日期',
            'capture_date.date' => '日期格式不正確',
            'capture_date.before_or_equal' => '日期不可晚於今天',
            'tribe.required' => '請選擇部落',
            'tribe.in' => '請選擇有效的部落',
            'capture_method.required' => '請選擇捕獲方式',
            'capture_method.max' => '捕獲方式不可超過 255 個字元',
            'place_name.prohibits' => '地名文字與既有地名不可同時送出',
        ];
    }

    public function attributes(): array
    {
        return ['capture_date' => '日期', 'tribe' => '部落', 'capture_method' => '捕獲方式', 'place_name' => '地名'];
    }
}
