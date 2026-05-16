<?php

use App\Services\CaptureRecordFieldValidator;
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
        ->and($createRules['capture_date'])->toBe('required|date|before_or_equal:today')
        ->and($createRules['notes'])->toBe('nullable|string|max:65535');
});

it('validates location field with shared rule set', function () {
    $validated = $this->validator->validateLocation('大武溪上游');

    expect($validated)->toBe(['location' => '大武溪上游']);
});

it('rejects future capture dates with shared validator message', function () {
    $this->validator->validateCaptureDate(now()->addDay()->toDateString());
})->throws(ValidationException::class, '捕獲日期不能是未來日期');

it('rejects overly long notes with shared validator message', function () {
    $this->validator->validateNotes(str_repeat('a', 65536));
})->throws(ValidationException::class, '備註內容過長，請縮短至65535字元以內');
