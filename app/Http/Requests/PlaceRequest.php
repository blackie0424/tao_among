<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['editor', 'admin'], true);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:191'], 'tao_name' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string']];
    }

    public function messages(): array
    {
        return [
            'name.required' => '請輸入地名',
            'name.max' => '地名不可超過 191 個字元',
            'tao_name.max' => '族語名稱不可超過 255 個字元',
        ];
    }

    public function attributes(): array
    {
        return ['name' => '地名', 'tao_name' => '族語名稱'];
    }
}
