<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Models\TribalClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Fish Model Relationships', function () {
    
    it('has many tribal classifications', function () {
        $fish = Fish::factory()->create();
        
        // Create tribal classifications with specific tribes to avoid unique constraint issues
        $classification1 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley'
        ]);
        
        $classification2 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'imowrod'
        ]);
        
        $classification3 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'ivalino'
        ]);

        expect($fish->tribalClassifications)->toHaveCount(3);
        expect($fish->tribalClassifications->first())->toBeInstanceOf(TribalClassification::class);
        
        foreach ($fish->tribalClassifications as $classification) {
            expect($classification->fish_id)->toBe($fish->id);
        }
    });

    it('has many capture records', function () {
        $fish = Fish::factory()->create();
        
        $captureRecords = CaptureRecord::factory()->count(2)->create([
            'fish_id' => $fish->id
        ]);

        expect($fish->captureRecords)->toHaveCount(2);
        expect($fish->captureRecords->first())->toBeInstanceOf(CaptureRecord::class);
        
        foreach ($fish->captureRecords as $record) {
            expect($record->fish_id)->toBe($fish->id);
        }
    });

    it('can have tribal classifications from different tribes', function () {
        $fish = Fish::factory()->create();
        
        $tribes = ['iraraley', 'imowrod', 'ivalino'];
        foreach ($tribes as $tribe) {
            TribalClassification::factory()->create([
                'fish_id' => $fish->id,
                'tribe' => $tribe
            ]);
        }

        expect($fish->tribalClassifications)->toHaveCount(3);
        
        $fishTribes = $fish->tribalClassifications->pluck('tribe')->toArray();
        expect($fishTribes)->toContain('iraraley');
        expect($fishTribes)->toContain('imowrod');
        expect($fishTribes)->toContain('ivalino');
    });

    it('can have capture records from different tribes', function () {
        $fish = Fish::factory()->create();
        
        $tribes = ['iraraley', 'imowrod'];
        foreach ($tribes as $tribe) {
            CaptureRecord::factory()->create([
                'fish_id' => $fish->id,
                'tribe' => $tribe
            ]);
        }

        expect($fish->captureRecords)->toHaveCount(2);
        
        $captureTribes = $fish->captureRecords->pluck('tribe')->toArray();
        expect($captureTribes)->toContain('iraraley');
        expect($captureTribes)->toContain('imowrod');
    });

    it('soft deletes related tribal classifications when fish is deleted', function () {
        $fish = Fish::factory()->create();
        
        // Create tribal classifications with specific tribes to avoid unique constraint issues
        $classification1 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley'
        ]);
        
        $classification2 = TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'imowrod'
        ]);

        $classificationIds = [$classification1->id, $classification2->id];

        $fish->delete();

        // Check that tribal classifications are soft deleted
        foreach ($classificationIds as $id) {
            expect(TribalClassification::find($id))->toBeNull();
            expect(TribalClassification::withTrashed()->find($id))->not->toBeNull();
            expect(TribalClassification::withTrashed()->find($id)->deleted_at)->not->toBeNull();
        }
    });

    it('soft deletes related capture records when fish is deleted', function () {
        $fish = Fish::factory()->create();
        $captureRecords = CaptureRecord::factory()->count(2)->create([
            'fish_id' => $fish->id
        ]);

        $recordIds = $captureRecords->pluck('id')->toArray();

        $fish->delete();

        // Check that capture records are soft deleted
        foreach ($recordIds as $id) {
            expect(CaptureRecord::find($id))->toBeNull();
            expect(CaptureRecord::withTrashed()->find($id))->not->toBeNull();
            expect(CaptureRecord::withTrashed()->find($id)->deleted_at)->not->toBeNull();
        }
    });

    it('can query fish with tribal classifications', function () {
        $fish1 = Fish::factory()->create(['name' => 'Fish 1']);
        $fish2 = Fish::factory()->create(['name' => 'Fish 2']);
        
        TribalClassification::factory()->create([
            'fish_id' => $fish1->id,
            'tribe' => 'iraraley',
            'food_category' => 'oyod'
        ]);

        $fishWithClassifications = Fish::with('tribalClassifications')->find($fish1->id);
        $fishWithoutClassifications = Fish::with('tribalClassifications')->find($fish2->id);

        expect($fishWithClassifications->tribalClassifications)->toHaveCount(1);
        expect($fishWithoutClassifications->tribalClassifications)->toHaveCount(0);
    });

    it('can query fish with capture records', function () {
        $fish1 = Fish::factory()->create(['name' => 'Fish 1']);
        $fish2 = Fish::factory()->create(['name' => 'Fish 2']);
        
        CaptureRecord::factory()->create([
            'fish_id' => $fish1->id,
            'tribe' => 'iraraley',
            'location' => 'Test Location'
        ]);

        $fishWithRecords = Fish::with('captureRecords')->find($fish1->id);
        $fishWithoutRecords = Fish::with('captureRecords')->find($fish2->id);

        expect($fishWithRecords->captureRecords)->toHaveCount(1);
        expect($fishWithoutRecords->captureRecords)->toHaveCount(0);
    });

    it('can load both tribal classifications and capture records', function () {
        $fish = Fish::factory()->create();
        
        // Create tribal classifications with specific tribes to avoid unique constraint issues
        TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley'
        ]);
        
        TribalClassification::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'imowrod'
        ]);
        
        CaptureRecord::factory()->count(3)->create(['fish_id' => $fish->id]);

        $fishWithRelations = Fish::with(['tribalClassifications', 'captureRecords'])->find($fish->id);

        expect($fishWithRelations->tribalClassifications)->toHaveCount(2);
        expect($fishWithRelations->captureRecords)->toHaveCount(3);
    });
});
