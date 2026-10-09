<?php

use App\Http\Requests\CaptureRecordRequest;
use App\Models\CaptureSession;
use App\Services\CaptureRecordFieldValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

function captureRecordCreateValidator(array $overrides = [])
{
    $request = new CaptureRecordRequest;

    return Validator::make([
        'image_filename' => 'capture.jpg',
        ...$overrides,
    ], $request->rules(), $request->messages(), $request->attributes());
}

it('accepts a real session for web capture record creation', function () {
    $session = CaptureSession::factory()->create();

    expect(captureRecordCreateValidator(['session_id' => $session->id])->passes())->toBeTrue();
});

it('accepts a complete legacy combo for web capture record creation', function () {
    $validator = captureRecordCreateValidator(['legacy_combo' => [
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location' => null,
    ]]);

    expect($validator->passes())->toBeTrue();
});

it('requires exactly one session source', function () {
    $session = CaptureSession::factory()->create();
    $combo = [
        'capture_date' => '2026-10-01',
        'tribe' => 'ivalino',
        'capture_method' => 'mamasil',
        'location' => null,
    ];

    $missing = captureRecordCreateValidator();
    $conflicting = captureRecordCreateValidator([
        'session_id' => $session->id,
        'legacy_combo' => $combo,
    ]);

    expect($missing->fails())->toBeTrue()
        ->and($missing->errors()->has('session_id'))->toBeTrue()
        ->and($missing->errors()->has('legacy_combo'))->toBeTrue()
        ->and($conflicting->fails())->toBeTrue()
        ->and($conflicting->errors()->has('session_id'))->toBeTrue()
        ->and($conflicting->errors()->has('legacy_combo'))->toBeTrue();
});

it('rejects client supplied context fields', function (string $field) {
    $session = CaptureSession::factory()->create();
    $validator = captureRecordCreateValidator([
        'session_id' => $session->id,
        $field => 'client value',
    ]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has($field))->toBeTrue();
})->with(['tribe', 'location', 'capture_method', 'capture_date']);

it('requires an image filename and validates optional image controls', function () {
    $session = CaptureSession::factory()->create();
    $request = new CaptureRecordRequest;

    $missing = Validator::make(['session_id' => $session->id], $request->rules());
    $invalid = captureRecordCreateValidator([
        'session_id' => $session->id,
        'image_position' => 'diagonal',
        'image_scale' => 3,
    ]);

    expect($missing->errors()->has('image_filename'))->toBeTrue()
        ->and($invalid->errors()->has('image_position'))->toBeTrue()
        ->and($invalid->errors()->has('image_scale'))->toBeTrue();
});

it('keeps the legacy LINE validation profile unchanged', function () {
    $service = app(CaptureRecordFieldValidator::class);
    $valid = [
        'image_filename' => 'line.jpg',
        'tribe' => 'ivalino',
        'location' => '測試地點',
        'capture_method' => 'mamasil',
        'capture_date' => '2026-10-01',
        'notes' => null,
    ];

    expect($service->rules())->toBe($service->legacyLineRules())
        ->and(Validator::make($valid, $service->legacyLineRules())->passes())->toBeTrue();
});
