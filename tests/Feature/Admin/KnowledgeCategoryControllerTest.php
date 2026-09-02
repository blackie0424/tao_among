<<?php

use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
    
    // 建立 4 個固定分類
    $this->seed(\Database\Seeders\KnowledgeCategorySeeder::class);
});

// --- 權限 ---

it('未登入者無法存取 knowledge-categories', function () {
    $this->get('/admin/knowledge-categories')->assertRedirect('/login');
});

it('editor 無法存取 knowledge-categories', function () {
    $this->actingAs($this->editor)->get('/admin/knowledge-categories')->assertStatus(403);
});

// --- Index ---

it('admin 可以瀏覽 knowledge-categories 列表', function () {
    $this->actingAs($this->admin)
        ->get('/admin/knowledge-categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KnowledgeCategories/Index')
            ->has('categories', 4)
        );
});

// --- Edit ---

it('admin 可以瀏覽 knowledge-category 編輯頁面', function () {
    $category = KnowledgeCategory::first();

    $this->actingAs($this->admin)
        ->get("/admin/knowledge-categories/{$category->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/KnowledgeCategories/Edit')
            ->where('category.id', $category->id)
        );
});

// --- Update ---

it('admin 可以更新 knowledge-category', function () {
    $category = KnowledgeCategory::first();

    $this->actingAs($this->admin)
        ->put("/admin/knowledge-categories/{$category->id}", [
            'title' => '更新後的標題',
            'is_published' => true,
        ])
        ->assertRedirect('/admin/knowledge-categories');

    $this->assertDatabaseHas('knowledge_categories', [
        'id' => $category->id,
        'title' => '更新後的標題',
        'is_published' => true,
    ]);
});

it('update 驗證：title 必填', function () {
    $category = KnowledgeCategory::first();

    $this->actingAs($this->admin)
        ->put("/admin/knowledge-categories/{$category->id}", [
            'title' => '',
        ])
        ->assertSessionHasErrors('title');
});

// --- Toggle Published ---

it('admin 可以切換分類的發布狀態', function () {
    $category = KnowledgeCategory::first();
    $originalStatus = $category->is_published;

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-categories/{$category->id}/toggle-published")
        ->assertRedirect();

    $category->refresh();
    expect($category->is_published)->toBe(!$originalStatus);
});

// --- Move Up ---

it('admin 可以上移分類', function () {
    $categories = KnowledgeCategory::orderBy('sort_order')->get();
    $second = $categories[1];
    $first = $categories[0];

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-categories/{$second->id}/move-up")
        ->assertRedirect();

    $second->refresh();
    $first->refresh();

    expect($second->sort_order)->toBe(0);
    expect($first->sort_order)->toBe(1);
});

it('已在最上方的分類無法上移', function () {
    $first = KnowledgeCategory::orderBy('sort_order')->first();

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-categories/{$first->id}/move-up")
        ->assertRedirect();

    $first->refresh();
    expect($first->sort_order)->toBe(0);
});

// --- Move Down ---

it('admin 可以下移分類', function () {
    $categories = KnowledgeCategory::orderBy('sort_order')->get();
    $first = $categories[0];
    $second = $categories[1];

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-categories/{$first->id}/move-down")
        ->assertRedirect();

    $first->refresh();
    $second->refresh();

    expect($first->sort_order)->toBe(1);
    expect($second->sort_order)->toBe(0);
});

it('已在最下方的分類無法下移', function () {
    $last = KnowledgeCategory::orderBy('sort_order', 'desc')->first();
    $originalOrder = $last->sort_order;

    $this->actingAs($this->admin)
        ->patch("/admin/knowledge-categories/{$last->id}/move-down")
        ->assertRedirect();

    $last->refresh();
    expect($last->sort_order)->toBe($originalOrder);
});
