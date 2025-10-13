<?php

use App\Http\Requests\TribalClassificationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

describe('TribalClassificationRequest Validation', function () {
    
    it('passes validation with valid data', function () {
        $request = new TribalClassificationRequest();
        
        $data = [
            'tribe' => 'iraraley',
            'food_category' => 'oyod',
            'processing_method' => '去魚鱗',
            'notes' => 'Test notes'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->passes())->toBeTrue();
    });

    it('requires tribe field', function () {
        $request = new TribalClassificationRequest();
        
        $data = [
            'food_category' => 'oyod',
            'processing_method' => '去魚鱗',
            'notes' => 'Test notes'
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('tribe'))->toBeTrue();
    });

    it('validates tribe field with valid values', function () {
        $request = new TribalClassificationRequest();
        $validTribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        foreach ($validTribes as $tribe) {
            $data = ['tribe' => $tribe];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('tribe'))->toBeFalse();
        }
    });

    it('rejects invalid tribe values', function () {
        $request = new TribalClassificationRequest();
        $invalidTribes = ['invalid_tribe', 'unknown', ''];
        
        foreach ($invalidTribes as $tribe) {
            $data = ['tribe' => $tribe];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('tribe'))->toBeTrue();
        }
    });

    it('validates food_category with valid values', function () {
        $request = new TribalClassificationRequest();
        $validCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        
        foreach ($validCategories as $category) {
            $data = [
                'tribe' => 'iraraley',
                'food_category' => $category
            ];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('food_category'))->toBeFalse();
        }
    });

    it('rejects invalid food_category values', function () {
        $request = new TribalClassificationRequest();
        $invalidCategories = ['invalid_category', 'unknown'];
        
        foreach ($invalidCategories as $category) {
            $data = [
                'tribe' => 'iraraley',
                'food_category' => $category
            ];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('food_category'))->toBeTrue();
        }
    });

    it('validates processing_method with valid values', function () {
        $request = new TribalClassificationRequest();
        $validMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        foreach ($validMethods as $method) {
            $data = [
                'tribe' => 'iraraley',
                'processing_method' => $method
            ];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('processing_method'))->toBeFalse();
        }
    });

    it('rejects invalid processing_method values', function () {
        $request = new TribalClassificationRequest();
        $invalidMethods = ['invalid_method', 'unknown'];
        
        foreach ($invalidMethods as $method) {
            $data = [
                'tribe' => 'iraraley',
                'processing_method' => $method
            ];
            $validator = Validator::make($data, $request->rules());
            
            expect($validator->errors()->has('processing_method'))->toBeTrue();
        }
    });

    it('allows null values for optional fields', function () {
        $request = new TribalClassificationRequest();
        
        $data = [
            'tribe' => 'iraraley',
            'food_category' => null,
            'processing_method' => null,
            'notes' => null
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->passes())->toBeTrue();
    });

    it('validates notes field length', function () {
        $request = new TribalClassificationRequest();
        
        // Test with very long notes (over 65535 characters)
        $longNotes = str_repeat('a', 65536);
        
        $data = [
            'tribe' => 'iraraley',
            'notes' => $longNotes
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('notes'))->toBeTrue();
    });

    it('accepts notes within length limit', function () {
        $request = new TribalClassificationRequest();
        
        // Test with notes at the limit (65535 characters)
        $longNotes = str_repeat('a', 65535);
        
        $data = [
            'tribe' => 'iraraley',
            'notes' => $longNotes
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('notes'))->toBeFalse();
    });

    it('rejects non-string notes', function () {
        $request = new TribalClassificationRequest();
        
        $data = [
            'tribe' => 'iraraley',
            'notes' => 12345
        ];

        $validator = Validator::make($data, $request->rules());
        
        expect($validator->errors()->has('notes'))->toBeTrue();
    });

    it('has correct custom messages', function () {
        $request = new TribalClassificationRequest();
        $messages = $request->messages();
        
        expect($messages)->toHaveKey('tribe.required');
        expect($messages)->toHaveKey('tribe.in');
        expect($messages)->toHaveKey('food_category.in');
        expect($messages)->toHaveKey('processing_method.in');
        expect($messages)->toHaveKey('notes.string');
        expect($messages)->toHaveKey('notes.max');
        
        expect($messages['tribe.required'])->toBe('請選擇部落');
        expect($messages['tribe.in'])->toBe('請選擇有效的部落');
    });

    it('has correct custom attributes', function () {
        $request = new TribalClassificationRequest();
        $attributes = $request->attributes();
        
        expect($attributes)->toHaveKey('tribe');
        expect($attributes)->toHaveKey('food_category');
        expect($attributes)->toHaveKey('processing_method');
        expect($attributes)->toHaveKey('notes');
        
        expect($attributes['tribe'])->toBe('部落');
        expect($attributes['food_category'])->toBe('飲食分類');
        expect($attributes['processing_method'])->toBe('處理方式');
        expect($attributes['notes'])->toBe('調查備註');
    });
});
