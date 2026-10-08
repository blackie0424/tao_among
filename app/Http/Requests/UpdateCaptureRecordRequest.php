<?php

namespace App\Http\Requests;

use App\Models\CaptureRecord;
use App\Services\CaptureRecordFieldValidator;
use Illuminate\Foundation\Http\FormRequest;

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
            return $validator->legacyLineRules(false);
        }

        return [
            'tribe' => ['prohibited'],
            'location' => ['prohibited'],
            'capture_method' => ['prohibited'],
            'capture_date' => ['prohibited'],
            'notes' => ['nullable', 'string', 'max:65535'],
            'image_filename' => ['nullable', 'string'],
            'image_position' => ['nullable', 'in:center,top,bottom,left,right'],
            'image_scale' => ['nullable', 'numeric', 'min:0.8', 'max:2.0'],
        ];
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
