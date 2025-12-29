<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergeFishRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // 暫時允許所有使用者，未來可加入權限控制
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
            'target_fish_id' => 'required|integer|exists:fish,id',
            'source_fish_ids' => 'required|array|min:1',
            'source_fish_ids.*' => 'required|integer|exists:fish,id|different:target_fish_id',
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
            'target_fish_id.required' => '請指定主魚類 ID',
            'target_fish_id.integer' => '主魚類 ID 必須為整數',
            'target_fish_id.exists' => '主魚類不存在',
            'source_fish_ids.required' => '請選擇要併入的魚類',
            'source_fish_ids.array' => '被併入魚類 ID 必須為陣列',
            'source_fish_ids.min' => '至少需要選擇一條要併入的魚類',
            'source_fish_ids.*.required' => '被併入魚類 ID 不可為空',
            'source_fish_ids.*.integer' => '被併入魚類 ID 必須為整數',
            'source_fish_ids.*.exists' => '被併入的魚類不存在',
            'source_fish_ids.*.different' => '無法將魚類合併到自己',
        ];
    }
}
