<?php

use App\Models\Fish;
use App\Models\TribalClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('TribalClassification Model', function () {
    
    it('can create a tribal classification', function () {
        $fish = Fish::factory()->create();
        
        $tribalClassification = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'food_category' => 'oyod',
            'processing_method' => '去魚鱗',
            'notes' => 'Test notes'
        ]);

        expect($tribalClassification)->toBeInstanceOf(TribalClassification::class);
        expect($tribalClassification->fish_id)->toBe($fish->id);
        expect($tribalClassification->tribe)->toBe('iraraley');
        expect($tribalClassification->food_category)->toBe('oyod');
        expect($tribalClassification->processing_method)->toBe('去魚鱗');
        expect($tribalClassification->notes)->toBe('Test notes');
    });

    it('belongs to a fish', function () {
        $fish = Fish::factory()->create();
        $tribalClassification = TribalClassification::factory()->create([
            'fish_id' => $fish->id
        ]);

        expect($tribalClassification->fish)->toBeInstanceOf(Fish::class);
        expect($tribalClassification->fish->id)->toBe($fish->id);
        expect($tribalClassification->fish->name)->toBe($fish->name);
    });

    it('can have empty string values for categories', function () {
        $fish = Fish::factory()->create();
        
        $tribalClassification = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'food_category' => '',
            'processing_method' => '',
        ]);

        expect($tribalClassification->food_category)->toBe('');
        expect($tribalClassification->processing_method)->toBe('');
    });

    it('can have null notes', function () {
        $tribalClassification = TribalClassification::factory()->withoutNotes()->create();

        expect($tribalClassification->notes)->toBeNull();
    });

    it('uses soft deletes', function () {
        $tribalClassification = TribalClassification::factory()->create();
        $id = $tribalClassification->id;

        $tribalClassification->delete();

        expect($tribalClassification->deleted_at)->not->toBeNull();
        expect(TribalClassification::find($id))->toBeNull();
        expect(TribalClassification::withTrashed()->find($id))->not->toBeNull();
    });

    it('has correct fillable attributes', function () {
        $tribalClassification = new TribalClassification();
        
        $expectedFillable = [
            'fish_id',
            'tribe',
            'food_category',
            'processing_method',
            'notes'
        ];

        expect($tribalClassification->getFillable())->toBe($expectedFillable);
    });

    it('casts dates correctly', function () {
        $tribalClassification = TribalClassification::factory()->create();

        expect($tribalClassification->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($tribalClassification->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can be created with all valid tribe values', function () {
        $validTribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        foreach ($validTribes as $tribe) {
            $tribalClassification = TribalClassification::factory()->forTribe($tribe)->create();
            expect($tribalClassification->tribe)->toBe($tribe);
        }
    });

    it('can be created with all valid food category values', function () {
        $validCategories = ['oyod', 'rahet', '不分類', '不食用', '?', ''];
        
        foreach ($validCategories as $category) {
            $tribalClassification = TribalClassification::factory()->withFoodCategory($category)->create();
            expect($tribalClassification->food_category)->toBe($category);
        }
    });

    it('can be created with all valid processing method values', function () {
        $validMethods = ['去魚鱗', '不去魚鱗', '剝皮', '不食用', '?', ''];
        
        foreach ($validMethods as $method) {
            $tribalClassification = TribalClassification::factory()->withProcessingMethod($method)->create();
            expect($tribalClassification->processing_method)->toBe($method);
        }
    });

    it('can store long notes', function () {
        $longNotes = str_repeat('這是一個很長的備註。', 100);
        
        $tribalClassification = TribalClassification::factory()->create([
            'notes' => $longNotes
        ]);

        expect($tribalClassification->notes)->toBe($longNotes);
        expect(strlen($tribalClassification->notes))->toBeGreaterThan(1000);
    });
});
