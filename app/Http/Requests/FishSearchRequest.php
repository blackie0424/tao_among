<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * FishSearchRequest — 後端搜尋請求驗證與正規化
 * Trace: FR-001, FR-002, FR-003, FR-006, FR-007, FR-013
 */
class FishSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 公開端點（FR-015）
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string'],
            'tribe' => ['nullable', 'string'],
            'capture_location' => ['nullable', 'string'],
            'capture_method' => ['nullable', 'string'],
            'processing_method' => ['nullable', 'string'],
            'food_category' => ['nullable', 'string'],
            'perPage' => ['nullable'],
            'last_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'last_id.integer' => 'INVALID_CURSOR',
            'last_id.min' => 'INVALID_CURSOR',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Trim 文字參數，空字串視為 null（FR-004）
        $textFields = ['name','tribe','capture_location','capture_method','processing_method','food_category'];
        $data = $this->all();
        foreach ($textFields as $f) {
            if ($this->has($f)) {
                $v = trim((string)$this->input($f));
                $data[$f] = ($v === '') ? null : $v;
            }
        }

        // perPage 正規化（FR-007/FR-013）：非正整數或超界 → default
        $default = (int)config('fish_search.per_page_default');
        $max = (int)config('fish_search.per_page_max');
        $raw = $this->input('perPage');
        if ($raw === null) {
            $data['perPage'] = $default;
        } else {
            $parsed = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $data['perPage'] = ($parsed === false || $parsed > $max) ? $default : (int)$parsed;
        }

        $this->replace($data);
    }

    protected function failedValidation(Validator $validator)
    {
        // 針對游標錯誤以 422 INVALID_CURSOR 回應（FR-006）
        throw new HttpResponseException(response()->json(['error' => 'INVALID_CURSOR'], 422));
    }

    public function cleaned(): array
    {
        return [
            'name' => $this->input('name'),
            'tribe' => $this->input('tribe'),
            'capture_location' => $this->input('capture_location'),
            'capture_method' => $this->input('capture_method'),
            'processing_method' => $this->input('processing_method'),
            'food_category' => $this->input('food_category'),
            'perPage' => (int)$this->input('perPage'),
            'last_id' => $this->input('last_id'),
        ];
    }
}
