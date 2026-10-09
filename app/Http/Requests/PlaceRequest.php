<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['editor', 'admin'], true);
    }

    public function rules(): array
    {
        $tribeRules = ['nullable', Rule::in(config('fish_options.tribes'))];
        if ($this->user()?->role !== 'admin') {
            $tribeRules[0] = 'required';
        }

        return [
            'name' => ['required', 'string', 'max:191'],
            'tribe' => $tribeRules,
            'tao_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '請輸入地名',
            'name.max' => '地名不可超過 191 個字元',
            'tribe.required' => '請選擇部落',
            'tribe.in' => '請選擇有效的部落',
            'tao_name.max' => '族語名稱不可超過 255 個字元',
        ];
    }

    public function attributes(): array
    {
        return ['name' => '地名', 'tribe' => '部落', 'tao_name' => '族語名稱'];
    }
}
