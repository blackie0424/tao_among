<?php

use App\Models\Topic;
use App\Models\TopicItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TopicSeeder::class);
    $this->topic = Topic::where('slug', 'bait')->first();
    $this->topic->update(['is_published' => true]);
});

// --- API: Topics ---

it('API 回傳已發布的知識分類', function () {
    // 設定部分分類為已發布
    Topic::where('slug', 'fish-guide')->update(['is_published' => true]);
    Topic::where('slug', 'bait')->update(['is_published' => true]);
    Topic::where('slug', 'fishing-method')->update(['is_published' => false]);

    $response = $this->getJson('/prefix/api/topics')
        ->assertOk()
        ->assertJsonCount(2);

    $data = $response->json();
    expect($data[0])->toHaveKeys(['id', 'title', 'slug', 'image_url', 'is_fish_category', 'sort_order']);
});

// --- Index (清單頁) ---

it('可以瀏覽知識分類清單頁', function () {
    TopicItem::factory()->count(3)->for($this->topic, 'topic')->published()->create();

    $this->get("/topics/{$this->topic->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Topic/Index')
            ->where('topic.slug', $this->topic->slug)
            ->has('items', 3)
        );
});

it('清單頁只顯示已發布的項目', function () {
    TopicItem::factory()->for($this->topic, 'topic')->published()->create(['title' => '已發布項目']);
    TopicItem::factory()->for($this->topic, 'topic')->create(['title' => '草稿項目', 'is_published' => false]);

    $this->get("/topics/{$this->topic->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.title', '已發布項目')
        );
});

it('清單頁按 sort_order 排序', function () {
    $item1 = TopicItem::factory()->for($this->topic, 'topic')->published()->create(['title' => '第三', 'sort_order' => 2]);
    $item2 = TopicItem::factory()->for($this->topic, 'topic')->published()->create(['title' => '第一', 'sort_order' => 0]);
    $item3 = TopicItem::factory()->for($this->topic, 'topic')->published()->create(['title' => '第二', 'sort_order' => 1]);

    $this->get("/topics/{$this->topic->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.title', '第一')
            ->where('items.1.title', '第二')
            ->where('items.2.title', '第三')
        );
});

it('不存在的分類回傳 404', function () {
    $this->get('/topics/non-existent-slug')
        ->assertNotFound();
});

it('未發布的分類回傳 404', function () {
    $unpublishedTopic = Topic::where('slug', 'fishing-method')->first();
    $unpublishedTopic->update(['is_published' => false]);

    $this->get("/topics/{$unpublishedTopic->slug}")
        ->assertNotFound();
});

// --- Show (詳細頁) ---

it('可以瀏覽知識項目詳細頁', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->published()->create([
        'title' => '測試項目',
        'description' => '這是詳細說明',
    ]);

    $this->get("/topics/{$this->topic->slug}/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Topic/Show')
            ->where('topic.slug', $this->topic->slug)
            ->where('item.id', $item->id)
            ->where('item.title', '測試項目')
            ->where('item.description', '這是詳細說明')
        );
});

it('未發布的項目回傳 404', function () {
    $item = TopicItem::factory()->for($this->topic, 'topic')->create(['is_published' => false]);

    $this->get("/topics/{$this->topic->slug}/{$item->id}")
        ->assertNotFound();
});

it('不存在的項目回傳 404', function () {
    $this->get("/topics/{$this->topic->slug}/99999")
        ->assertNotFound();
});

it('跨分類存取項目回傳 404', function () {
    $otherTopic = Topic::where('slug', 'fishing-method')->first();
    $otherTopic->update(['is_published' => true]);
    
    $item = TopicItem::factory()->for($otherTopic, 'topic')->published()->create();

    // 用錯誤的分類 slug 存取
    $this->get("/topics/{$this->topic->slug}/{$item->id}")
        ->assertNotFound();
});
