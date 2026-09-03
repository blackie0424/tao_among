<<?php

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
    
    // 建立 4 個固定分類
    $this->seed(\Database\Seeders\TopicSeeder::class);
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
