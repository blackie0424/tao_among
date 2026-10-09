<?php

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('refuses a destructive rollback before changing schema when duplicate name keys exist', function () {
    Place::factory()->create(['tribe' => 'ivalino', 'scope_key' => 'ivalino', 'name' => '岸邊', 'name_key' => '岸邊']);
    Place::factory()->create(['tribe' => 'yayo', 'scope_key' => 'yayo', 'name' => '岸邊', 'name_key' => '岸邊']);
    $migration = require database_path('migrations/2026_10_09_000001_add_scope_and_provisional_to_places.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, '有 1 組重複名稱')
        ->and(Schema::hasColumns('places', ['tribe', 'scope_key', 'is_provisional']))->toBeTrue()
        ->and(Place::where('name_key', '岸邊')->count())->toBe(2);
});

it('rolls back and reapplies clean data while preserving existing rows', function () {
    $place = Place::factory()->create(['name' => '唯一地名', 'name_key' => '唯一地名']);
    $migration = require database_path('migrations/2026_10_09_000001_add_scope_and_provisional_to_places.php');
    $migration->down();
    expect(Schema::hasColumns('places', ['tribe', 'scope_key', 'is_provisional']))->toBeFalse()->and(DB::table('places')->where('id', $place->id)->value('name'))->toBe('唯一地名');
    $migration->up();
    $row = DB::table('places')->where('id', $place->id)->first();
    expect(Schema::hasColumns('places', ['tribe', 'scope_key', 'is_provisional']))->toBeTrue()->and($row->tribe)->toBeNull()->and($row->scope_key)->toBe('')->and((bool) $row->is_provisional)->toBeFalse()->and($row->name)->toBe('唯一地名');
});
