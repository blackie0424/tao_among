<?php

use App\Models\IntroCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
});

// --- 權限 ---

it('未登入者無法存取 intro-categories', function () {
    $this->get('/admin/intro-categories')->assertRedirect('/login');
});

it('editor 無法存取 intro-categories', function () {
    $this->actingAs($this->editor)->get('/admin/intro-categories')->assertStatus(403);
});

// --- Index ---

it('admin 可以瀏覽 intro-categories 列表', function () {
    IntroCategory::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get('/admin/intro-categories')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IntroCategories/Index')
            ->has('categories', 3)
        );
});

// --- Create ---

it('admin 可以瀏覽 intro-category 新增頁面', function () {
    $this->actingAs($this->admin)
        ->get('/admin/intro-categories/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IntroCategories/Create')
        );
});

// --- Store ---

it('admin 可以新增 intro-category', function () {
    $this->actingAs($this->admin)
        ->post('/admin/intro-categories', [
            'name' => '達悟文化',
            'sort_order' => 1,
        ])
        ->assertRedirect('/admin/intro-categories');

    $this->assertDatabaseHas('intro_categories', ['name' => '達悟文化', 'sort_order' => 1]);
});

it('store 驗證：name 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/intro-categories', ['sort_order' => 0])
        ->assertSessionHasErrors('name');
});

// --- Edit ---

it('admin 可以瀏覽 intro-category 編輯頁面', function () {
    $category = IntroCategory::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/intro-categories/{$category->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IntroCategories/Edit')
            ->where('category.id', $category->id)
        );
});

// --- Update ---

it('admin 可以更新 intro-category', function () {
    $category = IntroCategory::factory()->create(['name' => '舊名稱']);

    $this->actingAs($this->admin)
        ->put("/admin/intro-categories/{$category->id}", [
            'name' => '新名稱',
            'sort_order' => 5,
        ])
        ->assertRedirect('/admin/intro-categories');

    $this->assertDatabaseHas('intro_categories', ['id' => $category->id, 'name' => '新名稱', 'sort_order' => 5]);
});

// --- Destroy ---

it('admin 可以刪除 intro-category', function () {
    $category = IntroCategory::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/intro-categories/{$category->id}")
        ->assertRedirect('/admin/intro-categories');

    $this->assertDatabaseMissing('intro_categories', ['id' => $category->id]);
});
