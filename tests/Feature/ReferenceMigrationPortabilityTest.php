<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the reference tables with the expected portable schema', function () {
    expect(Schema::hasTable('references'))->toBeTrue();
    expect(Schema::hasColumns('references', [
        'id',
        'name',
        'image_url',
        'external_url',
        'author',
        'status',
        'created_at',
        'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('reference_knowledge'))->toBeTrue();
    expect(Schema::hasColumns('reference_knowledge', [
        'id',
        'fish_id',
        'reference_id',
        'tribe',
        'content',
        'pages',
        'page_start',
        'page_end',
        'note',
        'created_by',
        'created_at',
        'updated_at',
        'deleted_at',
    ]))->toBeTrue();

    $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('reference_knowledge')"));

    expect($foreignKeys->pluck('from')->all())
        ->toEqualCanonicalizing(['fish_id', 'reference_id', 'created_by']);
});

it('keeps reference migrations free of database-specific ddl workarounds', function () {
    $migrationPaths = [
        'database/migrations/2026_05_21_000001_create_references_table.php',
        'database/migrations/2026_05_21_000002_create_reference_knowledge_table.php',
        'database/migrations/2026_05_22_000001_add_tribe_and_page_range_to_reference_knowledge_table.php',
    ];

    foreach ($migrationPaths as $migrationPath) {
        $contents = file_get_contents(base_path($migrationPath));

        expect($contents)->not->toContain('information_schema');
        expect($contents)->not->toContain('ALTER TABLE');
        expect($contents)->not->toContain('->after(');
        expect($contents)->not->toContain('DB::statement');
    }
});
