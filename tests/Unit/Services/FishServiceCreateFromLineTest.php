<?php

use App\Models\Fish;
use App\Models\CaptureRecord;
use App\Services\FishService;
use App\Contracts\StorageServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery as m;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->storage = m::mock(StorageServiceInterface::class);
    $this->service = new FishService($this->storage);
});

it('stores fish image as filename only, not a full URL', function () {
    $filenames = ['abc123.jpg', 'def456.jpg'];

    $fish = $this->service->createFishFromLine('鯽魚', $filenames);

    expect($fish->image)->toBe('abc123.jpg');
});

it('stores capture record image_path as filename only, not a full URL', function () {
    $filenames = ['abc123.jpg', 'def456.jpg'];

    $fish = $this->service->createFishFromLine('鯽魚', $filenames);

    $records = CaptureRecord::where('fish_id', $fish->id)->get();
    foreach ($records as $record) {
        expect($record->image_path)->not->toContain('http');
        expect($record->image_path)->not->toContain('://');
    }
});

it('creates one capture record per filename', function () {
    $filenames = ['a.jpg', 'b.jpg', 'c.jpg'];

    $fish = $this->service->createFishFromLine('旗魚', $filenames);

    expect(CaptureRecord::where('fish_id', $fish->id)->count())->toBe(3);
});

it('sets display_capture_record_id to the first capture record', function () {
    $filenames = ['first.jpg', 'second.jpg'];

    $fish = $this->service->createFishFromLine('石斑魚', $filenames);

    $firstRecord = CaptureRecord::where('fish_id', $fish->id)->orderBy('id')->first();
    expect($fish->display_capture_record_id)->toBe($firstRecord->id);
});

it('uses default name when name is null', function () {
    $filenames = ['unknown.jpg'];

    $fish = $this->service->createFishFromLine(null, $filenames);

    expect($fish->name)->toBe('我不知道');
});
