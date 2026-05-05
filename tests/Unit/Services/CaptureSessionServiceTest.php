<?php

use App\Models\CaptureRecord;
use App\Models\Fish;
use App\Services\CaptureSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CaptureSessionService();
});

it('returns distinct capture session combinations ordered by date descending', function () {
    $fish = Fish::factory()->create();

    CaptureRecord::factory()->create([
        'fish_id'        => $fish->id,
        'tribe'          => 'iranmeilek',
        'location'       => '大武溪',
        'capture_method' => 'mamasil',
        'capture_date'   => '2026-05-04',
        'notes'          => '同一批次備註',
    ]);
    CaptureRecord::factory()->create([
        'fish_id'        => $fish->id,
        'tribe'          => 'iranmeilek',
        'location'       => '大武溪',
        'capture_method' => 'mamasil',
        'capture_date'   => '2026-05-04',
        'notes'          => '同一批次備註',
    ]);
    CaptureRecord::factory()->create([
        'fish_id'        => $fish->id,
        'tribe'          => 'ivalino',
        'location'       => '新武呂溪',
        'capture_method' => 'mapazat',
        'capture_date'   => '2026-04-20',
        'notes'          => '另一批次備註',
    ]);

    $sessions = $this->service->getRecentSessions();

    expect($sessions)->toHaveCount(2);
    expect($sessions[0]['capture_date'])->toBe('2026-05-04');
    expect($sessions[1]['capture_date'])->toBe('2026-04-20');
});

it('each session contains tribe, location, capture_method, capture_date and record_count', function () {
    $fish = Fish::factory()->create();

    CaptureRecord::factory()->create([
        'fish_id'        => $fish->id,
        'tribe'          => 'ivalino',
        'location'       => '知本溪',
        'capture_method' => 'mapazat',
        'capture_date'   => '2026-05-01',
        'notes'          => '測試備註',
    ]);

    $sessions = $this->service->getRecentSessions();

    expect($sessions[0])->toHaveKeys(['tribe', 'location', 'capture_method', 'capture_date', 'notes', 'record_count']);
    expect($sessions[0]['tribe'])->toBe('ivalino');
    expect($sessions[0]['location'])->toBe('知本溪');
    expect($sessions[0]['capture_method'])->toBe('mapazat');
    expect($sessions[0]['notes'])->toBe('測試備註');
    expect($sessions[0]['record_count'])->toBe(1);
});

it('excludes sessions where location is LINE Bot', function () {
    $fish = Fish::factory()->create();

    CaptureRecord::factory()->create([
        'fish_id'        => $fish->id,
        'tribe'          => 'iraraley',
        'location'       => 'LINE Bot',
        'capture_method' => '未知',
        'capture_date'   => '2026-05-04',
        'notes'          => 'LINE 暫存',
    ]);
    CaptureRecord::factory()->create([
        'fish_id'        => $fish->id,
        'tribe'          => 'iranmeilek',
        'location'       => '大武溪',
        'capture_method' => 'mamasil',
        'capture_date'   => '2026-05-03',
        'notes'          => '真實紀錄',
    ]);

    $sessions = $this->service->getRecentSessions();

    expect($sessions)->toHaveCount(1);
    expect($sessions[0]['location'])->not->toBe('LINE Bot');
});

it('returns empty array when no capture records exist', function () {
    $sessions = $this->service->getRecentSessions();

    expect($sessions)->toBeArray()->toBeEmpty();
});

it('limits results to 20 sessions', function () {
    $fish = Fish::factory()->create();

    for ($i = 1; $i <= 25; $i++) {
        CaptureRecord::factory()->create([
            'fish_id'        => $fish->id,
            'tribe'          => 'ivalino',
            'location'       => "地點{$i}",
            'capture_method' => 'mamasil',
            'capture_date'   => '2026-05-01',
            'notes'          => "備註{$i}",
        ]);
    }

    $sessions = $this->service->getRecentSessions();

    expect(count($sessions))->toBeLessThanOrEqual(20);
});
