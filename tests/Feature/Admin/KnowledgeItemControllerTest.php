<?php

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
    
    $this->seed(\Database\Seeders\KnowledgeCategorySeeder::class);
    $this->category = KnowledgeCategory::first();
});

// --- 權限 ---

it('未登入者無法存取 knowledge-items', function () {
    $this->get('/admin/knowledge-items')->assertRedirect('/login');
});

it('editor 無法存取 knowledge-items', function () {
    $this->actingAs($this->editor)->get('/admin/knowledge-items')->assertStatus(403);
});

// --- Index ---

it('admin 可以瀏覽 knowledge-items 列表', function () {
    KnowledgeItem::factory()->count(3)->for($this->category, 'category')->create();

    $this->actingAs($this->admin)
        ->get('/admin/knowledge-items')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KnowledgeItems/Index')
            ->has('items.data', 3)
            ->has('categories', 4)
        );
});

it('admin 可以按分類篩選 knowledge-items', function () {
    KnowledgeItem::factory()->count(2)->for($this->category, 'category')->create();
    
    $otherCategory = KnowledgeCategory::where('id', '!=', $this->category->id)->first();
    KnowledgeItem::factory()->count(1)->for($otherCategory, 'category')->create();

    $this->actingAs($this->admin)
        ->get("/admin/knowledge-items?category_id={$this->category->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KnowledgeItems/Index')
            ->has('items.data', 2)
            ->where('selectedCategoryId', $this->category->id)
        );
});

// --- Create ---

it('admin 可以瀏覽 knowledge-item 新增頁面', function () {
    $this->actingAs($this->admin)
        ->get('/admin/knowledge-items/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KnowledgeItems/Create')
            ->has('categories', 4)
        );
});

// --- Store ---

it('admin 可以新增 knowledge-item', function () {
    $this->actingAs($this->admin)
        ->post('/admin/knowledge-items', [
            'knowledge_category_id' => $this->category->id,
            'title' => '飛魚',
            'description' => '蘭嶼最重要的魚類',
            'image_path' => 'knowledge-items/flying-fish.jpg',
            'is_published' => true,
        ])
        ->assertRedirect("/admin/knowledge-items?category_id={$this->category->id}");

    $item = KnowledgeItem::where('title', '飛魚')->first();
    expect($item)->not->toBeNull();
    expect($item->knowledge_category_id)->toBe($this->category->id);
    expect($item->description)->toBe('蘭嶼最重要的魚類');
    expect($item->sort_order)->toBe(0); // 自動計算為 0
});

it('store 自動計算 sort_order 為該分類最大值+1', function () {
    // 先建立一個項目
    KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 0]);
    KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->post('/admin/knowledge-items', [
            'knowledge_category_id' => $this->category->id,
            'title' => '新項目',
            'is_published' => false,
        ]);

    $newItem = KnowledgeItem::where('title', '新項目')->first();
    expect($newItem->sort_order)->toBe(2);
});

it('store 驗證：title 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/knowledge-items', [
            'knowledge_category_id' => $this->category->id,
        ])
        ->assertSessionHasErrors('title');
});

it('store 驗證：knowledge_category_id 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/knowledge-items', [
            'title' => '測試項目',
        ])
        ->assertSessionHasErrors('knowledge_category_id');
});

// --- Edit ---

it('admin 可以瀏覽 knowledge-item 編輯頁面', function () {
    $item = KnowledgeItem::factory()->for($this->category, 'category')->create();

    $this->actingAs($this->admin)
        ->get("/admin/knowledge-items/{$item->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KnowledgeItems/Edit')
            ->where('item.id', $item->id)
            ->has('categories', 4)
        );
});

// --- Update ---

it('admin 可以更新 knowledge-item', function () {
    $item = KnowledgeItem::factory()->for($this->category, 'category')->create();

    $this->actingAs($this->admin)
        ->put("/admin/knowledge-items/{$item->id}", [
            'knowledge_category_id' => $this->category->id,
            'title' => '更新後的標題',
            'description' => '更新後的說明',
            'is_published' => true,
        ])
        ->assertRedirect('/admin/knowledge-items');

    $this->assertDatabaseHas('knowledge_items', [
        'id' => $item->id,
        'title' => '更新後的標題',
        'description' => '更新後的說明',
    ]);
});

// --- Delete ---

it('admin 可以刪除 knowledge-item', function () {
    $item = KnowledgeItem::factory()->for($this->category, 'category')->create();

    $this->actingAs($this->admin)
        ->delete("/admin/knowledge-items/{$item->id}")
        ->assertRedirect('/admin/knowledge-items');

    $this->assertDatabaseMissing('knowledge_items', ['id' => $item->id]);
});

// --- Toggle Published ---

it('admin 可以切換項目的發布狀態', function () {
    $item = KnowledgeItem::factory()->for($this->category, 'category')->create(['is_published' => false]);

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-items/{$item->id}/toggle-published")
        ->assertRedirect();

    $item->refresh();
    expect($item->is_published)->toBe(true);
});

// --- Move Up ---

it('admin 可以上移項目（限定同分類）', function () {
    $first = KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 0]);
    $second = KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-items/{$second->id}/move-up")
        ->assertRedirect();

    $first->refresh();
    $second->refresh();

    expect($second->sort_order)->toBe(0);
    expect($first->sort_order)->toBe(1);
});

it('上移不會影響其他分類的項目', function () {
    $item1 = KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 0]);
    $item2 = KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 1]);
    
    $otherCategory = KnowledgeCategory::where('id', '!=', $this->category->id)->first();
    $otherItem = KnowledgeItem::factory()->for($otherCategory, 'category')->create(['sort_order' => 0]);

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-items/{$item2->id}/move-up");

    $otherItem->refresh();
    expect($otherItem->sort_order)->toBe(0); // 不受影響
});

// --- Move Down ---

it('admin 可以下移項目（限定同分類）', function () {
    $first = KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 0]);
    $second = KnowledgeItem::factory()->for($this->category, 'category')->create(['sort_order' => 1]);

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-items/{$first->id}/move-down")
        ->assertRedirect();

    $first->refresh();
    $second->refresh();

    expect($first->sort_order)->toBe(1);
    expect($second->sort_order)->toBe(0);
});
