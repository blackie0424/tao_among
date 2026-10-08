<?php

use App\Models\CaptureRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('rolls all three migrations back and forward while preserving nullable locations', function () {
    $record = CaptureRecord::factory()->create(['location' => null]);

    $places = require database_path('migrations/2026_10_07_000001_create_places_table.php');
    $sessions = require database_path('migrations/2026_10_07_000002_create_capture_sessions_table.php');
    $records = require database_path('migrations/2026_10_07_000003_add_session_id_and_nullable_location_to_capture_records.php');

    $records->down();
    expect(Schema::hasColumn('capture_records', 'session_id'))->toBeFalse()
        ->and(DB::table('capture_records')->where('id', $record->id)->value('location'))->toBe('');

    $sessions->down();
    $places->down();
    expect(Schema::hasTable('capture_sessions'))->toBeFalse()
        ->and(Schema::hasTable('places'))->toBeFalse();

    $places->up();
    $sessions->up();
    $records->up();

    DB::table('capture_records')->where('id', $record->id)->update(['location' => null]);
    expect(Schema::hasTable('places'))->toBeTrue()
        ->and(Schema::hasTable('capture_sessions'))->toBeTrue()
        ->and(Schema::hasColumn('capture_records', 'session_id'))->toBeTrue()
        ->and(DB::table('capture_records')->where('id', $record->id)->value('location'))->toBeNull();
});
