<?php

use App\Contracts\StorageServiceInterface;
use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use App\Services\TopicItemMediaManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->storage = $this->mock(StorageServiceInterface::class);
    $this->manager = app(TopicItemMediaManager::class);
});

it('does not delete S3 images when the outer database transaction rolls back', function () {
    $item = TopicItem::factory()->withImage('topic-items/rollback.jpg')->create();
    $this->storage->shouldNotReceive('delete');

    try {
        DB::transaction(function () use ($item): void {
            $this->manager->sync($item, []);
            throw new RuntimeException('force outer rollback');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('force outer rollback');
    }

    $this->assertDatabaseHas('topic_items', ['id' => $item->id]);
    $this->assertDatabaseHas('topic_item_media', [
        'topic_item_id' => $item->id,
        'source' => 'topic-items/rollback.jpg',
    ]);
});

it('deletes only unreferenced images after the outer transaction commits', function () {
    $item = TopicItem::factory()->create();
    $otherItem = TopicItem::factory()->create();
    $item->media()->create([
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/unique.jpg',
        'sort_order' => 0,
    ]);
    $item->media()->create([
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/shared.jpg',
        'sort_order' => 1,
    ]);
    $otherItem->media()->create([
        'type' => TopicItemMedia::TYPE_IMAGE,
        'source' => 'topic-items/shared.jpg',
        'sort_order' => 0,
    ]);

    $this->storage->shouldReceive('delete')->once()->with('topic-items/unique.jpg')->andReturnTrue();
    $this->storage->shouldNotReceive('delete')->with('topic-items/shared.jpg');

    $this->manager->deleteForItem($item);

    $this->assertDatabaseMissing('topic_items', ['id' => $item->id]);
    $this->assertDatabaseHas('topic_item_media', [
        'topic_item_id' => $otherItem->id,
        'source' => 'topic-items/shared.jpg',
    ]);
});

it('keeps committed database changes when S3 deletion fails', function () {
    $item = TopicItem::factory()->withImage('topic-items/failure.jpg')->create();
    $this->storage->shouldReceive('delete')->once()
        ->with('topic-items/failure.jpg')
        ->andThrow(new RuntimeException('storage unavailable'));
    Log::shouldReceive('error')->once();

    $this->manager->deleteForItem($item);

    $this->assertDatabaseMissing('topic_items', ['id' => $item->id]);
    $this->assertDatabaseMissing('topic_item_media', [
        'topic_item_id' => $item->id,
        'source' => 'topic-items/failure.jpg',
    ]);
});
