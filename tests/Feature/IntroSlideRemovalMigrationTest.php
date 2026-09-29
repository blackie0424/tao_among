<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('drops and fully restores the intro slide tables', function () {
    expect(Schema::hasTable('intro_slides'))->toBeFalse()
        ->and(Schema::hasTable('intro_categories'))->toBeFalse();

    $migration = require base_path('database/migrations/2026_09_29_000001_drop_intro_slide_tables.php');
    $migration->down();

    expect(Schema::hasColumns('intro_categories', [
        'id',
        'name',
        'sort_order',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasColumns('intro_slides', [
        'id',
        'category_id',
        'title',
        'body',
        'media_type',
        'media_path',
        'sort_order',
        'is_published',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    $foreignKeyColumns = collect(Schema::getForeignKeys('intro_slides'))
        ->flatMap(fn (array $foreignKey) => $foreignKey['columns'])
        ->all();

    expect($foreignKeyColumns)->toContain('category_id');

    $migration->up();

    expect(Schema::hasTable('intro_slides'))->toBeFalse()
        ->and(Schema::hasTable('intro_categories'))->toBeFalse();
});
