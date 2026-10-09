<?php

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
