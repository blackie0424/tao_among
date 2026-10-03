<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Topic;
use App\Models\TopicItem;
use App\Models\TopicItemMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->storageMock = $this->mock(StorageServiceInterface::class);
    $this->storageMock->shouldReceive('getImageFolder')->zeroOrMoreTimes()->andReturn('images');
    $this->storageMock->shouldReceive('getWebpFolder')->zeroOrMoreTimes()->andReturn('webp');
    $this->storageMock->shouldReceive('getUrl')->zeroOrMoreTimes()
        ->andReturnUsing(fn (string $folder, string $path) => "https://storage.test/{$folder}/{$path}");
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
    $this->topic = Topic::first();
});

it('未登入者無法存取 topic-items', function () {
    $this->get('/admin/topic-items')->assertRedirect('/login');
});

it('editor 無法存取 topic-items', function () {
    $this->actingAs($this->editor)->get('/admin/topic-items')->assertForbidden();
});

it('admin 可以瀏覽及篩選 topic-items 列表', function () {
    TopicItem::factory()->count(2)->for($this->topic, 'topic')->create();
    $otherTopic = Topic::whereKeyNot($this->topic->id)->first();
    TopicItem::factory()->for($otherTopic, 'topic')->create();

    $this->actingAs($this->admin)
        ->get("/admin/topic-items?topic_id={$this->topic->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TopicItems/Index')
            ->has('items.data', 2)
            ->has('topics', 4)
            ->where('selectedTopicId', $this->topic->id));
});

it('admin 可以瀏覽新增與編輯頁面，編輯頁包含有序媒體', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $item->media()->create(['type' => 'youtube', 'source' => 'dQw4w9WgXcQ', 'sort_order' => 1]);
    $image = $item->media()->create(['type' => 'image', 'source' => 'topic-items/photo.jpg', 'sort_order' => 0]);

    $this->actingAs($this->admin)->get('/admin/topic-items/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Admin/TopicItems/Create')->has('topics', 4));

    $this->actingAs($this->admin)->get("/admin/topic-items/{$item->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TopicItems/Edit')
            ->where('item.id', $item->id)
            ->where('item.media.0.id', $image->id)
            ->has('item.media', 2));
});

it('admin 可以新增含多張圖片及多支 YouTube 影片的 topic-item', function () {
    $this->actingAs($this->admin)->post('/admin/topic-items', [
        'topic_id' => $this->topic->id,
        'title' => '多媒體項目',
        'is_published' => true,
        'media' => [
            ['type' => 'image', 'source' => 'topic-items/first.jpg'],
            ['type' => 'youtube', 'source' => 'https://youtu.be/dQw4w9WgXcQ'],
            ['type' => 'image', 'source' => 'topic-items/second.jpg'],
            ['type' => 'youtube', 'source' => 'https://www.youtube.com/watch?v=9bZkp7q19f0'],
        ],
    ])->assertRedirect("/admin/topic-items?topic_id={$this->topic->id}");

    $item = TopicItem::where('title', '多媒體項目')->firstOrFail();
    expect($item->sort_order)->toBe(0)
        ->and($item->media()->pluck('sort_order')->all())->toBe([0, 1, 2, 3])
        ->and($item->media()->where('type', 'youtube')->pluck('source')->all())
        ->toBe(['dQw4w9WgXcQ', '9bZkp7q19f0']);
});

it('store 未帶媒體仍可建立 topic-item', function () {
    $this->actingAs($this->admin)->post('/admin/topic-items', [
        'topic_id' => $this->topic->id,
        'title' => '純文字項目',
    ])->assertRedirect();

    expect(TopicItem::where('title', '純文字項目')->firstOrFail()->media()->exists())->toBeFalse();
});

it('store 驗證必填欄位及媒體型別', function () {
    $this->actingAs($this->admin)->post('/admin/topic-items', [
        'media' => [['type' => 'vimeo', 'source' => 'https://vimeo.com/123']],
    ])->assertSessionHasErrors(['topic_id', 'title', 'media.0.type']);
});

it('接受兩種 YouTube 網址並拒絕無法辨識的連結', function (string $url, bool $valid) {
    $response = $this->actingAs($this->admin)->post('/admin/topic-items', [
        'topic_id' => $this->topic->id,
        'title' => 'YouTube 驗證 '.md5($url),
        'media' => [['type' => 'youtube', 'source' => $url]],
    ]);

    $valid
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors('media.0.source');
})->with([
    'youtube.com' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', true],
    'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ', true],
    'invalid' => ['https://example.com/video', false],
]);

it('update 新增、刪除並依陣列順序重新排序媒體', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $removed = $item->media()->create(['type' => 'image', 'source' => 'topic-items/remove.jpg', 'sort_order' => 0]);
    $kept = $item->media()->create(['type' => 'youtube', 'source' => 'dQw4w9WgXcQ', 'sort_order' => 1]);
    $this->storageMock->shouldReceive('delete')->once()->with($removed->source)->andReturnTrue();

    $this->actingAs($this->admin)->put("/admin/topic-items/{$item->id}", [
        'topic_id' => $this->topic->id,
        'title' => '更新後項目',
        'media' => [
            ['id' => $kept->id, 'type' => 'youtube', 'source' => $kept->youtube_url],
            ['type' => 'image', 'source' => 'topic-items/new.jpg'],
        ],
    ])->assertRedirect();

    expect($item->media()->pluck('source')->all())->toBe(['dQw4w9WgXcQ', 'topic-items/new.jpg'])
        ->and($item->media()->pluck('sort_order')->all())->toBe([0, 1]);
});

it('更新圖片路徑時刪除舊 S3 檔案，路徑相同時不刪', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $image = $item->media()->create(['type' => 'image', 'source' => 'topic-items/original.jpg', 'sort_order' => 0]);
    $this->storageMock->shouldReceive('delete')->once()->with('topic-items/original.jpg')->andReturnTrue();

    $this->actingAs($this->admin)->put("/admin/topic-items/{$item->id}", [
        'topic_id' => $this->topic->id,
        'title' => $item->title,
        'media' => [['id' => $image->id, 'type' => 'image', 'source' => 'topic-items/replacement.jpg']],
    ])->assertRedirect();

    $this->actingAs($this->admin)->put("/admin/topic-items/{$item->id}", [
        'topic_id' => $this->topic->id,
        'title' => $item->title,
        'media' => [['id' => $image->id, 'type' => 'image', 'source' => 'topic-items/replacement.jpg']],
    ])->assertRedirect();
});

it('媒體不屬於該項目或 id 重複時拒絕更新', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $other = TopicItem::factory()->for($this->topic, 'topic')->create();
    $foreignMedia = $other->media()->create(['type' => 'image', 'source' => 'topic-items/foreign.jpg', 'sort_order' => 0]);

    $this->actingAs($this->admin)->put("/admin/topic-items/{$item->id}", [
        'topic_id' => $this->topic->id,
        'title' => $item->title,
        'media' => [
            ['id' => $foreignMedia->id, 'type' => 'image', 'source' => $foreignMedia->source],
            ['id' => $foreignMedia->id, 'type' => 'image', 'source' => $foreignMedia->source],
        ],
    ])->assertSessionHasErrors(['media.0.id', 'media.1.id']);
});

it('刪除 topic-item 時只清理所有圖片媒體並連動刪除媒體資料', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $item->media()->create(['type' => 'image', 'source' => 'topic-items/a.jpg', 'sort_order' => 0]);
    $item->media()->create(['type' => 'youtube', 'source' => 'dQw4w9WgXcQ', 'sort_order' => 1]);
    $item->media()->create(['type' => 'image', 'source' => 'topic-items/b.jpg', 'sort_order' => 2]);
    $this->storageMock->shouldReceive('delete')->once()->with('topic-items/a.jpg')->andReturnTrue();
    $this->storageMock->shouldReceive('delete')->once()->with('topic-items/b.jpg')->andReturnTrue();

    $this->actingAs($this->admin)->delete("/admin/topic-items/{$item->id}")->assertRedirect();

    $this->assertDatabaseMissing('topic_items', ['id' => $item->id]);
    $this->assertDatabaseMissing('topic_item_media', ['topic_item_id' => $item->id]);
});

it('S3 圖片刪除失敗不阻擋媒體及項目刪除', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $item->media()->create(['type' => 'image', 'source' => 'topic-items/failure.jpg', 'sort_order' => 0]);
    $this->storageMock->shouldReceive('delete')->once()->andThrow(new RuntimeException('storage unavailable'));

    $this->actingAs($this->admin)->delete("/admin/topic-items/{$item->id}")->assertRedirect();
    $this->assertDatabaseMissing('topic_items', ['id' => $item->id]);
});

it('admin 可以更新資料、切換發布狀態及調整項目順序', function () {
    $first = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 0, 'is_published' => false]);
    $second = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)->put("/admin/topic-items/{$first->id}", [
        'topic_id' => $this->topic->id,
        'title' => '更新標題',
        'media' => [],
    ])->assertRedirect();
    $this->actingAs($this->admin)->patch("/admin/topic-items/{$first->id}/toggle-published")->assertRedirect();
    $this->actingAs($this->admin)->patch("/admin/topic-items/{$first->id}/move-down")->assertRedirect();

    expect($first->refresh()->title)->toBe('更新標題')
        ->and($first->is_published)->toBeTrue()
        ->and($first->sort_order)->toBe(1)
        ->and($second->refresh()->sort_order)->toBe(0);
});
