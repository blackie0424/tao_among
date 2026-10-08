<?php

use App\Services\CaptureRecordFieldValidator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->validator = app(CaptureRecordFieldValidator::class);
});

it('builds shared rules for create and update flows', function () {
    $createRules = $this->validator->rules(true);
    $updateRules = $this->validator->rules(false);

    expect($createRules['image_filename'])->toBe('required|string')
        ->and($updateRules['image_filename'])->toBe('nullable|string')
        ->and($createRules['location'])->toBe('required|string|max:255')
        ->and($createRules['capture_date'])->toBe('required|date|before_or_equal:'.now('Asia/Taipei')->toDateString())
        ->and($createRules['notes'])->toBe('nullable|string|max:65535');
});

it('validates location field with shared rule set', function () {
    $validated = $this->validator->validateLocation('大武溪上游');

    expect($validated)->toBe(['location' => '大武溪上游']);
});

it('accepts the Taiwan calendar date in the existing capture record flow', function () {
    try {
        Carbon::setTestNow('2026-10-07 16:00:00 UTC');
        expect($this->validator->validateCaptureDate('2026-10-08'))
            ->toBe(['capture_date' => '2026-10-08']);
    } finally {
        Carbon::setTestNow();
    }
});

it('rejects a date after the Taiwan calendar date in the existing flow', function () {
    try {
        Carbon::setTestNow('2026-10-07 16:00:00 UTC');
        $this->validator->validateCaptureDate('2026-10-09');
    } finally {
        Carbon::setTestNow();
    }
})->throws(ValidationException::class, '捕獲日期不能是未來日期');

it('rejects overly long notes with shared validator message', function () {
    $this->validator->validateNotes(str_repeat('a', 65536));
})->throws(ValidationException::class, '備註內容過長，請縮短至65535字元以內');
