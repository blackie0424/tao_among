<?php

use App\Models\Topic;
use App\Models\TopicItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
    
    $this->seed(\Database\Seeders\TopicSeeder::class);
    $this->topic = Topic::first();
});

// --- 權限 ---

it('未登入者無法存取 topic-items', function () {
    $this->get('/admin/topic-items')->assertRedirect('/login');
});

it('editor 無法存取 topic-items', function () {
    $this->actingAs($this->editor)->get('/admin/topic-items')->assertStatus(403);
});

// --- Index ---

it('admin 可以瀏覽 topic-items 列表', function () {
    TopicItem::factory()->count(3)->for($this->topic, 'topic')->create();

    $this->actingAs($this->admin)
        ->get('/admin/topic-items')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TopicItems/Index')
            ->has('items.data', 3)
            ->has('topics', 4)
        );
});

it('admin 可以按分類篩選 topic-items', function () {
    TopicItem::factory()->count(2)->for($this->topic, 'topic')->create();
    
    $otherTopic = Topic::where('id', '!=', $this->topic->id)->first();
    TopicItem::factory()->count(1)->for($otherTopic, 'topic')->create();

    $this->actingAs($this->admin)
        ->get("/admin/topic-items?topic_id={$this->topic->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TopicItems/Index')
            ->has('items.data', 2)
            ->where('selectedTopicId', $this->topic->id)
        );
});

// --- Create ---

it('admin 可以瀏覽 topic-item 新增頁面', function () {
    $this->actingAs($this->admin)
        ->get('/admin/topic-items/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TopicItems/Create')
            ->has('topics', 4)
        );
});

// --- Store ---

it('admin 可以新增 topic-item', function () {
    $this->actingAs($this->admin)
        ->post('/admin/topic-items', [
            'topic_id' => $this->topic->id,
            'title' => '飛魚',
            'description' => '蘭嶼最重要的魚類',
            'image_path' => 'topic-items/flying-fish.jpg',
            'is_published' => true,
        ])
        ->assertRedirect("/admin/topic-items?topic_id={$this->topic->id}");

    $item = TopicItem::where('title', '飛魚')->first();
    expect($item)->not->toBeNull();
    expect($item->topic_id)->toBe($this->topic->id);
    expect($item->description)->toBe('蘭嶼最重要的魚類');
    expect($item->sort_order)->toBe(0); // 自動計算為 0
});

it('store 自動計算 sort_order 為該分類最大值+1', function () {
    // 先建立一個項目
    TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 0]);
    TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->post('/admin/topic-items', [
            'topic_id' => $this->topic->id,
            'title' => '新項目',
            'image_path' => 'topic-items/new-item.jpg',
            'is_published' => false,
        ]);

    $newItem = TopicItem::where('title', '新項目')->first();
    expect($newItem->sort_order)->toBe(2);
});

it('store 驗證：title 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/topic-items', [
            'topic_id' => $this->topic->id,
        ])
        ->assertSessionHasErrors('title');
});

it('store 驗證：topic_id 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/topic-items', [
            'title' => '測試項目',
        ])
        ->assertSessionHasErrors('topic_id');
});

// --- Edit ---

it('admin 可以瀏覽 topic-item 編輯頁面', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();

    $this->actingAs($this->admin)
        ->get("/admin/topic-items/{$item->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/TopicItems/Edit')
            ->where('item.id', $item->id)
            ->has('topics', 4)
        );
});

// --- Update ---

it('admin 可以更新 topic-item', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();

    $this->actingAs($this->admin)
        ->put("/admin/topic-items/{$item->id}", [
            'topic_id' => $this->topic->id,
            'title' => '更新後的標題',
            'description' => '更新後的說明',
            'is_published' => true,
        ])
        ->assertRedirect("/admin/topic-items?topic_id={$this->topic->id}");

    $this->assertDatabaseHas('topic_items', [
        'id' => $item->id,
        'title' => '更新後的標題',
        'description' => '更新後的說明',
    ]);
});

// --- Delete ---

it('admin 可以刪除 topic-item', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create();
    $topicId = $item->topic_id;

    $this->actingAs($this->admin)
        ->delete("/admin/topic-items/{$item->id}")
        ->assertRedirect("/admin/topic-items?topic_id={$topicId}");

    $this->assertDatabaseMissing('topic_items', ['id' => $item->id]);
});

// --- Toggle Published ---

it('admin 可以切換項目的發布狀態', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create(['is_published' => false]);

    $this->actingAs($this->admin)
        ->patch("/admin/topic-items/{$item->id}/toggle-published")
        ->assertRedirect();

    $item->refresh();
    expect($item->is_published)->toBe(true);
});

// --- Move Up ---

it('admin 可以上移項目（限定同分類）', function () {
    $first = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 0]);
    $second = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->patch("/admin/topic-items/{$second->id}/move-up")
        ->assertRedirect();

    $first->refresh();
    $second->refresh();

    expect($second->sort_order)->toBe(0);
    expect($first->sort_order)->toBe(1);
});

it('上移不會影響其他分類的項目', function () {
    $item1 = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 0]);
    $item2 = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 1]);
    
    $otherTopic = Topic::where('id', '!=', $this->topic->id)->first();
    $otherItem = TopicItem::factory()->for($otherTopic, 'topic')->create(['sort_order' => 0]);

    $this->actingAs($this->admin)
        ->patch("/admin/topic-items/{$item2->id}/move-up");

    $otherItem->refresh();
    expect($otherItem->sort_order)->toBe(0); // 不受影響
});

// --- Move Down ---

it('admin 可以下移項目（限定同分類）', function () {
    $first = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 0]);
    $second = TopicItem::factory()->for($this->topic, 'topic')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->patch("/admin/topic-items/{$first->id}/move-down")
        ->assertRedirect();

    $first->refresh();
    $second->refresh();

    expect($first->sort_order)->toBe(1);
    expect($second->sort_order)->toBe(0);
});
