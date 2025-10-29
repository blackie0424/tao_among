<?php

use App\Models\Fish;
use App\Models\FishNote;
use App\Models\CaptureRecord;
use App\Models\TribalClassification;
use App\Models\FishAudio;
use App\Services\FishService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('loads fish details with eager relations and groups notes', function () {
    // Arrange: create fish with related records
    $fish = Fish::factory()->create([
        'image' => 'foo.jpg',
        'audio_filename' => null,
    ]);

    FishNote::factory()->create(['fish_id' => $fish->id, 'note_type' => 'A']);
    FishNote::factory()->create(['fish_id' => $fish->id, 'note_type' => 'A']);
    FishNote::factory()->create(['fish_id' => $fish->id, 'note_type' => 'B']);
    TribalClassification::factory()->create(['fish_id' => $fish->id]);
    CaptureRecord::factory()->create(['fish_id' => $fish->id]);
    FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'voice.mp3']);

    // Avoid external HTTP/WebP check
    Http::fake(['*' => Http::response('', 404)]);

    // Act
    $service = app(FishService::class);
    $details = $service->getFishDetails($fish->id);

    // Assert basics
    expect($details)->toHaveKeys(['fish', 'tribalClassifications', 'captureRecords', 'fishNotes']);
    expect($details['fish']->id)->toBe($fish->id);

    // Media decoration
    expect($details['fish']->image)->toBeString()->not->toBe('');
    expect($details['fish']->audio_filename)->toBeNull(); // null-safe for audio

    // Relations exists
    expect($details['tribalClassifications']->count())->toBe(1);
    expect($details['captureRecords']->count())->toBe(1);

    // Grouped notes keys
    expect(array_keys($details['fishNotes']))->toEqualCanonicalizing(['A', 'B']);
    expect($details['fishNotes']['A'])->toHaveCount(2);
    expect($details['fishNotes']['B'])->toHaveCount(1);
});
