<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Topic;
use App\Models\TopicItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->storageMock = $this->mock(StorageServiceInterface::class);
    $this->storageMock->shouldReceive('getImageFolder')->andReturn('images')->byDefault();
    $this->storageMock->shouldReceive('getWebpFolder')->andReturn('webp')->byDefault();
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
});

// --- 權限 ---

it('未登入者無法存取 topics', function () {
    $this->get('/admin/topics')->assertRedirect('/login');
});

it('editor 無法存取 topics', function () {
    $this->actingAs($this->editor)->get('/admin/topics')->assertStatus(403);
});

// --- Index ---

it('admin 可以瀏覽 topics 列表', function () {
    $this->actingAs($this->admin)
        ->get('/admin/topics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Topics/Index')
            ->has('topics', 4)
        );
});

it('topics index eager loads item counts with a fixed number of item queries', function () {
    TopicItem::factory()->count(2)->for(Topic::firstOrFail(), 'topic')->create();

    $phase = 'baseline';
    $itemQueries = ['baseline' => 0, 'expanded' => 0];
    DB::listen(function ($query) use (&$phase, &$itemQueries): void {
        if (str_contains(strtolower($query->sql), 'topic_items')) {
            $itemQueries[$phase]++;
        }
    });

    $this->actingAs($this->admin)
        ->get('/admin/topics')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('topics.0.items_count', 2)
        );

    Topic::factory()->count(3)->create();
    $phase = 'expanded';

    $this->get('/admin/topics')->assertOk();

    expect($itemQueries['baseline'])->toBe(1)
        ->and($itemQueries['expanded'])->toBe(1);
});

// --- Edit ---

it('admin 可以瀏覽 topic 編輯頁面', function () {
    $topic = Topic::first();

    $this->actingAs($this->admin)
        ->get("/admin/topics/{$topic->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Topics/Edit')
            ->where('topic.id', $topic->id)
        );
});

// --- Update ---

it('admin 可以更新 topic', function () {
    $topic = Topic::first();

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => '更新後的標題',
            'is_published' => true,
        ])
        ->assertRedirect('/admin/topics');

    $this->assertDatabaseHas('topics', [
        'id' => $topic->id,
        'title' => '更新後的標題',
        'is_published' => true,
    ]);
});

it('admin 可以移除 topic 目前圖片', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => 'topics/original.jpg']);
    $this->storageMock->shouldReceive('delete')
        ->once()
        ->with('topics/original.jpg')
        ->andReturnTrue();

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'remove_image' => true,
        ])
        ->assertRedirect('/admin/topics');

    $topic->refresh();
    expect($topic->image_path)->toBeNull()
        ->and($topic->image_url)->toBeNull();
});

it('更新 topic 時未帶圖片欄位會保留原圖', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => 'topics/original.jpg']);
    $this->storageMock->shouldNotReceive('delete');

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => '只更新標題',
        ])
        ->assertRedirect('/admin/topics');

    $topic->refresh();
    expect($topic->title)->toBe('只更新標題')
        ->and($topic->image_path)->toBe('topics/original.jpg');
});

it('同時移除並更新 topic 圖片時以新圖片為準', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => 'topics/original.jpg']);
    $this->storageMock->shouldReceive('delete')
        ->once()
        ->with('topics/original.jpg')
        ->andReturnTrue();

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'remove_image' => true,
            'image_path' => 'topics/replacement.jpg',
        ])
        ->assertRedirect('/admin/topics');

    $topic->refresh();
    expect($topic->image_path)->toBe('topics/replacement.jpg');
});

it('更新 topic 時圖片路徑未改變不會刪除檔案', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => 'topics/original.jpg']);
    $this->storageMock->shouldNotReceive('delete');

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'image_path' => 'topics/original.jpg',
        ])
        ->assertRedirect('/admin/topics');

    expect($topic->refresh()->image_path)->toBe('topics/original.jpg');
});

it('移除沒有圖片的 topic 不會呼叫刪除服務', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => null]);
    $this->storageMock->shouldNotReceive('delete');

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'remove_image' => true,
        ])
        ->assertRedirect('/admin/topics');

    expect($topic->refresh()->image_path)->toBeNull();
});

it('移除 topic 圖片時刪除舊檔失敗仍會清空圖片', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => 'topics/original.jpg']);
    $this->storageMock->shouldReceive('delete')
        ->once()
        ->with('topics/original.jpg')
        ->andThrow(new RuntimeException('storage unavailable'));

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'remove_image' => true,
        ])
        ->assertRedirect('/admin/topics');

    expect($topic->refresh()->image_path)->toBeNull();
});

it('更新 topic 圖片時刪除舊檔失敗仍會使用新圖片', function () {
    $topic = Topic::first();
    $topic->update(['image_path' => 'topics/original.jpg']);
    $this->storageMock->shouldReceive('delete')
        ->once()
        ->with('topics/original.jpg')
        ->andThrow(new RuntimeException('storage unavailable'));

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'image_path' => 'topics/replacement.jpg',
        ])
        ->assertRedirect('/admin/topics');

    expect($topic->refresh()->image_path)->toBe('topics/replacement.jpg');
});

it('update 驗證：remove_image 必須是布林值', function () {
    $topic = Topic::first();

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => $topic->title,
            'remove_image' => 'invalid',
        ])
        ->assertSessionHasErrors('remove_image');
});

it('update 驗證：title 必填', function () {
    $topic = Topic::first();

    $this->actingAs($this->admin)
        ->put("/admin/topics/{$topic->id}", [
            'title' => '',
        ])
        ->assertSessionHasErrors('title');
});

// --- Toggle Published ---

it('admin 可以切換分類的發布狀態', function () {
    $topic = Topic::first();
    $originalStatus = $topic->is_published;

    $this->actingAs($this->admin)
        ->patch("/admin/topics/{$topic->id}/toggle-published")
        ->assertRedirect();

    $topic->refresh();
    expect($topic->is_published)->toBe(!$originalStatus);
});

// --- Move Up ---

it('admin 可以上移分類', function () {
    $topics = Topic::orderBy('sort_order')->get();
    $second = $topics[1];
    $first = $topics[0];

    $this->actingAs($this->admin)
        ->patch("/admin/topics/{$second->id}/move-up")
        ->assertRedirect();

    $second->refresh();
    $first->refresh();

    expect($second->sort_order)->toBe(0);
    expect($first->sort_order)->toBe(1);
});

it('已在最上方的分類無法上移', function () {
    $first = Topic::orderBy('sort_order')->first();

    $this->actingAs($this->admin)
        ->patch("/admin/topics/{$first->id}/move-up")
        ->assertRedirect();

    $first->refresh();
    expect($first->sort_order)->toBe(0);
});

// --- Move Down ---

it('admin 可以下移分類', function () {
    $topics = Topic::orderBy('sort_order')->get();
    $first = $topics[0];
    $second = $topics[1];

    $this->actingAs($this->admin)
        ->patch("/admin/topics/{$first->id}/move-down")
        ->assertRedirect();

    $first->refresh();
    $second->refresh();

    expect($first->sort_order)->toBe(1);
    expect($second->sort_order)->toBe(0);
});

it('已在最下方的分類無法下移', function () {
    $last = Topic::orderBy('sort_order', 'desc')->first();
    $originalOrder = $last->sort_order;

    $this->actingAs($this->admin)
        ->patch("/admin/topics/{$last->id}/move-down")
        ->assertRedirect();

    $last->refresh();
    expect($last->sort_order)->toBe($originalOrder);
});
