<?php

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\KnowledgeCategorySeeder::class);
    $this->category = KnowledgeCategory::where('slug', 'bait')->first();
    $this->category->update(['is_published' => true]);
});

// --- API: Knowledge Categories ---

it('API 回傳已發布的知識分類', function () {
    // 設定部分分類為已發布
    KnowledgeCategory::where('slug', 'fish-guide')->update(['is_published' => true]);
    KnowledgeCategory::where('slug', 'bait')->update(['is_published' => true]);
    KnowledgeCategory::where('slug', 'fishing-method')->update(['is_published' => false]);

    $response = $this->getJson('/prefix/api/knowledge-categories')
        ->assertOk()
        ->assertJsonCount(2);

    $data = $response->json();
    expect($data[0])->toHaveKeys(['id', 'title', 'slug', 'image_url', 'is_fish_category', 'sort_order']);
});

// --- Index (清單頁) ---

it('可以瀏覽知識分類清單頁', function () {
    KnowledgeItem::factory()->count(3)->for($this->category, 'category')->published()->create();

    $this->get("/knowledge/{$this->category->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Index')
            ->where('category.slug', $this->category->slug)
            ->has('items', 3)
        );
});

it('清單頁只顯示已發布的項目', function () {
    KnowledgeItem::factory()->for($this->category, 'category')->published()->create(['title' => '已發布項目']);
    KnowledgeItem::factory()->for($this->category, 'category')->create(['title' => '草稿項目', 'is_published' => false]);

    $this->get("/knowledge/{$this->category->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('items', 1)
            ->where('items.0.title', '已發布項目')
        );
});

it('清單頁按 sort_order 排序', function () {
    $item1 = KnowledgeItem::factory()->for($this->category, 'category')->published()->create(['title' => '第三', 'sort_order' => 2]);
    $item2 = KnowledgeItem::factory()->for($this->category, 'category')->published()->create(['title' => '第一', 'sort_order' => 0]);
    $item3 = KnowledgeItem::factory()->for($this->category, 'category')->published()->create(['title' => '第二', 'sort_order' => 1]);

    $this->get("/knowledge/{$this->category->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.title', '第一')
            ->where('items.1.title', '第二')
            ->where('items.2.title', '第三')
        );
});

it('不存在的分類回傳 404', function () {
    $this->get('/knowledge/non-existent-slug')
        ->assertNotFound();
});

it('未發布的分類回傳 404', function () {
    $unpublishedCategory = KnowledgeCategory::where('slug', 'fishing-method')->first();
    $unpublishedCategory->update(['is_published' => false]);

    $this->get("/knowledge/{$unpublishedCategory->slug}")
        ->assertNotFound();
});

// --- Show (詳細頁) ---

it('可以瀏覽知識項目詳細頁', function () {
    $item = KnowledgeItem::factory()->for($this->category, 'category')->published()->create([
        'title' => '測試項目',
        'description' => '這是詳細說明',
    ]);

    $this->get("/knowledge/{$this->category->slug}/{$item->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Show')
            ->where('category.slug', $this->category->slug)
            ->where('item.id', $item->id)
            ->where('item.title', '測試項目')
            ->where('item.description', '這是詳細說明')
        );
});

it('未發布的項目回傳 404', function () {
    $item = KnowledgeItem::factory()->for($this->category, 'category')->create(['is_published' => false]);

    $this->get("/knowledge/{$this->category->slug}/{$item->id}")
        ->assertNotFound();
});

it('不存在的項目回傳 404', function () {
    $this->get("/knowledge/{$this->category->slug}/99999")
        ->assertNotFound();
});

it('跨分類存取項目回傳 404', function () {
    $otherCategory = KnowledgeCategory::where('slug', 'fishing-method')->first();
    $otherCategory->update(['is_published' => true]);
    
    $item = KnowledgeItem::factory()->for($otherCategory, 'category')->published()->create();

    // 用錯誤的分類 slug 存取
    $this->get("/knowledge/{$this->category->slug}/{$item->id}")
        ->assertNotFound();
});
