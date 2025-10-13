<?php

use App\Http\Requests\CaptureRecordRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

describe('CaptureRecordRequest Validation', function () {
    
    it('passes validation with valid data for POST request', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => 'Test notes'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->passes())->toBeTrue();
    });

    it('passes validation with valid data for PUT request', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('PUT');
        
        $data = [
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => 'Test notes'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->passes())->toBeTrue();
    });

    it('requires image_filename for POST request', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('image_filename'))->toBeTrue();
    });

    it('does not require image_filename for PUT request', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('PUT');
        
        $data = [
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->passes())->toBeTrue();
    });

    it('requires tribe field', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('tribe'))->toBeTrue();
    });

    it('validates tribe field with valid values', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        $validTribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        foreach ($validTribes as $tribe) {
            $data = [
                'image_filename' => 'test-image.jpg',
                'tribe' => $tribe,
                'location' => 'Test Location',
                'capture_method' => '網捕',
                'capture_date' => '2024-01-15'
            ];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('tribe'))->toBeFalse();
        }
    });

    it('rejects invalid tribe values', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        $invalidTribes = ['invalid_tribe', 'unknown', ''];
        
        foreach ($invalidTribes as $tribe) {
            $data = [
                'image_filename' => 'test-image.jpg',
                'tribe' => $tribe,
                'location' => 'Test Location',
                'capture_method' => '網捕',
                'capture_date' => '2024-01-15'
            ];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('tribe'))->toBeTrue();
        }
    });

    it('requires location field', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('location'))->toBeTrue();
    });

    it('validates location field length', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $longLocation = str_repeat('a', 256);
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => $longLocation,
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('location'))->toBeTrue();
    });

    it('requires capture_method field', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('capture_method'))->toBeTrue();
    });

    it('validates capture_method field length', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $longMethod = str_repeat('a', 256);
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => $longMethod,
            'capture_date' => '2024-01-15'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('capture_method'))->toBeTrue();
    });

    it('requires capture_date field', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('capture_date'))->toBeTrue();
    });

    it('validates capture_date is a valid date', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => 'invalid-date'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('capture_date'))->toBeTrue();
    });

    it('rejects future dates for capture_date', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $futureDate = now()->addDays(1)->format('Y-m-d');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => $futureDate
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('capture_date'))->toBeTrue();
    });

    it('accepts today date for capture_date', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $todayDate = now()->format('Y-m-d');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => $todayDate
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('capture_date'))->toBeFalse();
    });

    it('allows null notes', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => null
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->passes())->toBeTrue();
    });

    it('validates notes field length', function () {
        $request = new CaptureRecordRequest();
        $request->setMethod('POST');
        
        $longNotes = str_repeat('a', 65536);
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => $longNotes
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('notes'))->toBeTrue();
    });

    it('has correct custom messages', function () {
        $request = new CaptureRecordRequest();
        $messages = $request->messages();
        
        expect($messages)->toHaveKey('image_filename.required');
        expect($messages)->toHaveKey('tribe.required');
        expect($messages)->toHaveKey('location.required');
        expect($messages)->toHaveKey('capture_method.required');
        expect($messages)->toHaveKey('capture_date.required');
        expect($messages)->toHaveKey('capture_date.before_or_equal');
        
        expect($messages['image_filename.required'])->toBe('請上傳捕獲照片');
        expect($messages['tribe.required'])->toBe('請選擇捕獲部落');
        expect($messages['capture_date.before_or_equal'])->toBe('捕獲日期不能是未來日期');
    });

    it('has correct custom attributes', function () {
        $request = new CaptureRecordRequest();
        $attributes = $request->attributes();
        
        expect($attributes)->toHaveKey('image_filename');
        expect($attributes)->toHaveKey('tribe');
        expect($attributes)->toHaveKey('location');
        expect($attributes)->toHaveKey('capture_method');
        expect($attributes)->toHaveKey('capture_date');
        expect($attributes)->toHaveKey('notes');
        
        expect($attributes['image_filename'])->toBe('捕獲照片');
        expect($attributes['tribe'])->toBe('捕獲部落');
        expect($attributes['location'])->toBe('捕獲地點');
        expect($attributes['capture_method'])->toBe('捕獲方式');
        expect($attributes['capture_date'])->toBe('捕獲日期');
        expect($attributes['notes'])->toBe('備註');
    });
});
