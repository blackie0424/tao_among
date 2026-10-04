<?php

use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function topicItemMediaMigration(): object
{
    return require base_path('database/migrations/2026_10_03_000003_migrate_topic_item_images_to_media.php');
}

it('migrates legacy image paths and restores the first ordered image on rollback', function () {
    $migration = topicItemMediaMigration();
    $migration->down();

    $topicId = Topic::factory()->create()->id;
    $withImageId = DB::table('topic_items')->insertGetId([
        'topic_id' => $topicId,
        'title' => '有舊圖片',
        'image_path' => 'topic-items/legacy.jpg',
        'sort_order' => 0,
        'is_published' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $withoutImageId = DB::table('topic_items')->insertGetId([
        'topic_id' => $topicId,
        'title' => '沒有舊圖片',
        'image_path' => null,
        'sort_order' => 1,
        'is_published' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->up();

    expect(Schema::hasColumn('topic_items', 'image_path'))->toBeFalse()
        ->and(DB::table('topic_item_media')->where('topic_item_id', $withImageId)->get()->all())->toHaveCount(1)
        ->and(DB::table('topic_item_media')->where('topic_item_id', $withImageId)->value('source'))->toBe('topic-items/legacy.jpg')
        ->and(DB::table('topic_item_media')->where('topic_item_id', $withoutImageId)->exists())->toBeFalse();

    DB::table('topic_item_media')->where('topic_item_id', $withImageId)->update(['sort_order' => 1]);
    DB::table('topic_item_media')->insert([
        'topic_item_id' => $withImageId,
        'type' => 'image',
        'source' => 'topic-items/first.jpg',
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration->down();

    expect(Schema::hasColumn('topic_items', 'image_path'))->toBeTrue()
        ->and(DB::table('topic_items')->where('id', $withImageId)->value('image_path'))->toBe('topic-items/first.jpg')
        ->and(DB::table('topic_items')->where('id', $withoutImageId)->value('image_path'))->toBeNull()
        ->and(Schema::hasTable('topic_item_media'))->toBeTrue();

    $mediaCount = DB::table('topic_item_media')->where('topic_item_id', $withImageId)->count();
    $migration->up();

    expect(DB::table('topic_item_media')->where('topic_item_id', $withImageId)->count())->toBe($mediaCount);
});
