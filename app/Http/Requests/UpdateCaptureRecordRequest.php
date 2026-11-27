<?php

namespace App\Http\Requests;

use App\Models\CaptureRecord;
use App\Services\CaptureRecordFieldValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCaptureRecordRequest extends FormRequest
{
    private ?CaptureRecord $captureRecord = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $record = $this->captureRecord();
        $validator = app(CaptureRecordFieldValidator::class);

        if (! $record->session_id) {
            return $validator->legacyLineRules(false) + $validator->framingRules();
        }

        return [
            'tribe' => ['prohibited'],
            'location' => ['prohibited'],
            'capture_method' => ['prohibited'],
            'capture_date' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'image_filename' => ['nullable', 'string'],
        ] + $validator->framingRules();
    }

    /** 照片調整值的四個欄位只能整組一起送（含四個 null＝還原）；送了其中幾個，不論值是否為 null 都不合法 */
    public function after(): array
    {
        return [function (Validator $validator) {
            $sent = array_filter(CaptureRecordFieldValidator::FRAMING_FIELDS, fn (string $field) => $this->exists($field));

            if ($sent === [] || count($sent) === count(CaptureRecordFieldValidator::FRAMING_FIELDS)) {
                return;
            }

            foreach (array_diff(CaptureRecordFieldValidator::FRAMING_FIELDS, $sent) as $missing) {
                $validator->errors()->add($missing, '照片調整的四個值必須一起提供');
            }
        }];
    }

    public function messages(): array
    {
        $messages = app(CaptureRecordFieldValidator::class)->messages();
        $message = '此紀錄屬於某個情境，日期、地點、部落與方式請到情境修改';

        foreach (['tribe', 'location', 'capture_method', 'capture_date'] as $field) {
            $messages["{$field}.prohibited"] = $message;
        }

        return $messages;
    }

    public function attributes(): array
    {
        return app(CaptureRecordFieldValidator::class)->attributes();
    }

    public function captureRecord(): CaptureRecord
    {
        return $this->captureRecord ??= CaptureRecord::query()
            ->where('fish_id', $this->route('id'))
            ->whereKey($this->route('record_id'))
            ->firstOrFail();
    }
}
