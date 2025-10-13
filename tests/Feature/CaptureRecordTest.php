<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CaptureRecord Model', function () {
    
    it('can create a capture record', function () {
        $fish = Fish::factory()->create();
        
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => 'Test capture notes'
        ]);

        expect($captureRecord)->toBeInstanceOf(CaptureRecord::class);
        expect($captureRecord->fish_id)->toBe($fish->id);
        expect($captureRecord->image_path)->toBe('test-image.jpg');
        expect($captureRecord->tribe)->toBe('iraraley');
        expect($captureRecord->location)->toBe('Test Location');
        expect($captureRecord->capture_method)->toBe('網捕');
        expect($captureRecord->capture_date->format('Y-m-d'))->toBe('2024-01-15');
        expect($captureRecord->notes)->toBe('Test capture notes');
    });

    it('belongs to a fish', function () {
        $fish = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id
        ]);

        expect($captureRecord->fish)->toBeInstanceOf(Fish::class);
        expect($captureRecord->fish->id)->toBe($fish->id);
        expect($captureRecord->fish->name)->toBe($fish->name);
    });

    it('casts capture_date to date', function () {
        $captureRecord = CaptureRecord::factory()->create([
            'capture_date' => '2024-01-15'
        ]);

        expect($captureRecord->capture_date)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($captureRecord->capture_date->format('Y-m-d'))->toBe('2024-01-15');
    });

    it('casts created_at and updated_at to datetime', function () {
        $captureRecord = CaptureRecord::factory()->create();

        expect($captureRecord->created_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
        expect($captureRecord->updated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });

    it('can have null notes', function () {
        $captureRecord = CaptureRecord::factory()->withoutNotes()->create();

        expect($captureRecord->notes)->toBeNull();
    });

    it('uses soft deletes', function () {
        $captureRecord = CaptureRecord::factory()->create();
        $id = $captureRecord->id;

        $captureRecord->delete();

        expect($captureRecord->deleted_at)->not->toBeNull();
        expect(CaptureRecord::find($id))->toBeNull();
        expect(CaptureRecord::withTrashed()->find($id))->not->toBeNull();
    });

    it('has correct fillable attributes', function () {
        $captureRecord = new CaptureRecord();
        
        $expectedFillable = [
            'fish_id',
            'image_path',
            'tribe',
            'location',
            'capture_method',
            'capture_date',
            'notes'
        ];

        expect($captureRecord->getFillable())->toBe($expectedFillable);
    });

    it('has image_url in appends', function () {
        $captureRecord = new CaptureRecord();
        
        expect($captureRecord->getAppends())->toContain('image_url');
    });

    it('can be created with all valid tribe values', function () {
        $validTribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];
        
        foreach ($validTribes as $tribe) {
            $captureRecord = CaptureRecord::factory()->forTribe($tribe)->create();
            expect($captureRecord->tribe)->toBe($tribe);
        }
    });

    it('can store various capture methods', function () {
        $methods = ['網捕', '釣魚', '陷阱', '徒手捕捉', '魚叉'];
        
        foreach ($methods as $method) {
            $captureRecord = CaptureRecord::factory()->withMethod($method)->create();
            expect($captureRecord->capture_method)->toBe($method);
        }
    });

    it('can store long location names', function () {
        $longLocation = str_repeat('很長的地點名稱', 10);
        
        $captureRecord = CaptureRecord::factory()->atLocation($longLocation)->create();

        expect($captureRecord->location)->toBe($longLocation);
    });

    it('can store long notes', function () {
        $longNotes = str_repeat('這是一個很長的捕獲備註。', 100);
        
        $captureRecord = CaptureRecord::factory()->create([
            'notes' => $longNotes
        ]);

        expect($captureRecord->notes)->toBe($longNotes);
        expect(strlen($captureRecord->notes))->toBeGreaterThan(1000);
    });

    it('handles various image path formats', function () {
        $imagePaths = [
            'simple.jpg',
            'folder/image.png',
            'deep/folder/structure/image.webp',
            'uuid-12345-67890.jpg'
        ];
        
        foreach ($imagePaths as $imagePath) {
            $captureRecord = CaptureRecord::factory()->create([
                'image_path' => $imagePath
            ]);
            expect($captureRecord->image_path)->toBe($imagePath);
        }
    });

    it('can handle today capture date', function () {
        $captureRecord = CaptureRecord::factory()->today()->create();
        
        expect($captureRecord->capture_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'));
    });
});
