<?php

use App\Models\Topic;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function topicItemImagePathMigration(): object
{
    return require base_path('database/migrations/2026_10_03_000001_make_topic_item_image_path_nullable.php');
}

it('allows topic items without an image after migrating up', function () {
    $migration = topicItemImagePathMigration();
    $migration->down();
    $migration->up();

    $id = DB::table('topic_items')->insertGetId([
        'topic_id' => Topic::factory()->create()->id,
        'title' => '無圖片項目',
        'image_path' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('topic_items')->where('id', $id)->value('image_path'))->toBeNull();
});

it('replaces null image paths before restoring the not null constraint', function () {
    $itemId = DB::table('topic_items')->insertGetId([
        'topic_id' => Topic::factory()->create()->id,
        'title' => '回滾無圖片項目',
        'image_path' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = topicItemImagePathMigration();
    $migration->down();

    expect(DB::table('topic_items')->where('id', $itemId)->value('image_path'))->toBe('');

    expect(fn () => DB::table('topic_items')->insert([
        'topic_id' => Topic::factory()->create()->id,
        'title' => '不可為空圖片項目',
        'image_path' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $migration->up();
});
