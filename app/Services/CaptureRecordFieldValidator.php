<?php

namespace App\Services;

use App\Support\CaptureDate;
use Illuminate\Support\Facades\Validator;

class CaptureRecordFieldValidator
{
    /**
     * @return array<string, string>
     */
    public function rules(bool $requireImageFilename = true): array
    {
        return $this->legacyLineRules($requireImageFilename);
    }

    /** @return array<string, string|array<int, string>> */
    public function webSessionCreateRules(bool $requireImageFilename = true): array
    {
        return [
            'session_id' => ['required_without:legacy_combo', 'nullable', 'integer', 'exists:capture_sessions,id', 'prohibits:legacy_combo'],
            'legacy_combo' => ['required_without:session_id', 'nullable', 'array', 'prohibits:session_id'],
            'legacy_combo.capture_date' => ['required_with:legacy_combo', 'date'],
            'legacy_combo.tribe' => ['required_with:legacy_combo', 'in:ivalino,iranmeilek,imowrod,iratay,yayo,iraraley'],
            'legacy_combo.capture_method' => ['required_with:legacy_combo', 'string', 'max:255'],
            'legacy_combo.location' => ['nullable', 'string', 'max:255'],
            'tribe' => ['prohibited'],
            'location' => ['prohibited'],
            'capture_method' => ['prohibited'],
            'capture_date' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'image_filename' => [$requireImageFilename ? 'required' : 'nullable', 'string'],
            'image_position' => ['nullable', 'in:center,top,bottom,left,right'],
            'image_scale' => ['nullable', 'numeric', 'min:0.8', 'max:2.0'],
        ];
    }

    /** @return array<string, string> */
    public function legacyLineRules(bool $requireImageFilename = true): array
    {
        return [
            'tribe' => 'required|in:ivalino,iranmeilek,imowrod,iratay,yayo,iraraley',
            'location' => 'required|string|max:255',
            'capture_method' => 'required|string|max:255',
            'capture_date' => 'required|date|before_or_equal:'.CaptureDate::today(),
            'notes' => 'nullable|string|max:65535',
            'image_filename' => $requireImageFilename ? 'required|string' : 'nullable|string',
            'image_position' => 'nullable|in:center,top,bottom,left,right',
            'image_scale' => 'nullable|numeric|min:0.8|max:2.0',
        ];
    }

    /**
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
            'notes.max' => '備註內容過長，請縮短至65535字元以內',
            'image_position.in' => '圖片位置必須是有效的選項',
            'image_scale.numeric' => '圖片縮放比例必須是數字',
            'image_scale.min' => '圖片縮放比例最小為 0.8',
            'image_scale.max' => '圖片縮放比例最大為 2.0',
            'session_id.required_without' => '請選擇情境',
            'session_id.exists' => '這個情境已不存在，請重新選擇',
            'session_id.prohibits' => '情境與舊資料只能擇一',
            'legacy_combo.required_without' => '請選擇情境',
            'legacy_combo.prohibits' => '情境與舊資料只能擇一',
            'tribe.prohibited' => '部落由情境帶入，不能另行指定',
            'location.prohibited' => '地點由情境帶入，不能另行指定',
            'capture_method.prohibited' => '方式由情境帶入，不能另行指定',
            'capture_date.prohibited' => '日期由情境帶入，不能另行指定',
        ];
    }

    /**
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
            'notes' => '備註',
            'image_position' => '圖片顯示位置',
            'image_scale' => '圖片縮放比例',
        ];
    }

    /**
     * @return array{location:string}
     */
    public function validateLocation(string $location): array
    {
        return $this->validateField('location', $location);
    }

    /**
     * @return array{capture_date:string}
     */
    public function validateCaptureDate(string $captureDate): array
    {
        return $this->validateField('capture_date', $captureDate);
    }

    /**
     * @return array{notes:?string}
     */
    public function validateNotes(?string $notes): array
    {
        return $this->validateField('notes', $notes);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateSharedData(array $data, bool $requireImageFilename = true): array
    {
        return Validator::make(
            $data,
            $this->rules($requireImageFilename),
            $this->messages(),
            $this->attributes()
        )->validate();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateField(string $field, mixed $value): array
    {
        return Validator::make(
            [$field => $value],
            [$field => $this->rules(false)[$field]],
            $this->messages(),
            $this->attributes()
        )->validate();
    }
}
