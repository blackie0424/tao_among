<?php

use App\Models\Fish;
use App\Services\CaptureRecordBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CaptureRecordBatchService();
});

it('creates one capture record per filename with shared payload', function () {
    $fish = Fish::factory()->create();

    $records = $this->service->createForFish(
        $fish,
        ['first.jpg', 'second.jpg'],
        [
            'tribe'          => 'ivalino',
            'location'       => '大武溪上游',
            'capture_method' => 'mapazat',
            'capture_date'   => '2026-05-15',
            'notes'          => '同批次上傳',
        ]
    );

    expect($records)->toHaveCount(2);
    expect($records[0]->fish_id)->toBe($fish->id);
    expect($records[0]->image_path)->toBe('first.jpg');
    expect($records[0]->tribe)->toBe('ivalino');
    expect($records[0]->location)->toBe('大武溪上游');
    expect($records[0]->capture_method)->toBe('mapazat');
    expect($records[0]->notes)->toBe('同批次上傳');
    expect($records[1]->image_path)->toBe('second.jpg');
});

it('throws validation exception when no filenames are provided', function () {
    $fish = Fish::factory()->create();

    $this->service->createForFish(
        $fish,
        [],
        [
            'tribe'          => 'ivalino',
            'location'       => '大武溪上游',
            'capture_method' => 'mapazat',
            'capture_date'   => '2026-05-15',
        ]
    );
})->throws(ValidationException::class, '請上傳捕獲照片');

it('throws validation exception when shared payload is invalid', function () {
    $fish = Fish::factory()->create();

    $this->service->createForFish(
        $fish,
        ['first.jpg'],
        [
            'tribe'          => 'ivalino',
            'location'       => '大武溪上游',
            'capture_method' => 'mapazat',
            'capture_date'   => now()->addDay()->toDateString(),
            'notes'          => null,
        ]
    );
})->throws(ValidationException::class, '捕獲日期不能是未來日期');
