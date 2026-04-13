<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\User;
use App\Models\TribalClassification;

uses(RefreshDatabase::class);

// =========================================================
// 權限控制
// =========================================================

describe('Dashboard 路由權限', function () {

    it('未登入應重導至登入頁', function () {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    });

    it('一般登入使用者（非 admin）應被拒絕（403）', function () {
        $user = User::factory()->lineViewer()->create();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(403);
    });

    it('admin 使用者可存取 dashboard（200）', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    });
});

// =========================================================
// Props 結構
// =========================================================

describe('Dashboard props 結構', function () {

    it('回傳 Inertia Dashboard 頁面，包含所有必要 props', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->has('tribes')
                ->has('selectedTribe')
                ->has('fishStats')
                ->has('captureStats')
                ->has('tribalStats')
                ->has('audioStats')
                ->has('noteStats')
        );
    });

    it('fishStats 包含 total、with_capture_record、with_audio、with_tribal_classification', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->has('fishStats.total')
                ->has('fishStats.with_capture_record')
                ->has('fishStats.with_audio')
                ->has('fishStats.with_tribal_classification')
        );
    });

    it('tribalStats 預設模式（iraraley）包含 total 與 by_food_category', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->has('tribalStats.total')
                ->has('tribalStats.by_food_category')
                ->has('tribalStats.by_processing_method')
                ->where('tribalStats.by_tribe', [])
        );
    });

    it('selectedTribe 在無帶參數時預設為 iraraley', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->where('selectedTribe', 'iraraley')
        );
    });

    it('帶 tribe 參數時 selectedTribe prop 正確', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/dashboard?tribe=ivalino');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->where('selectedTribe', 'ivalino')
        );
    });
});

// =========================================================
// 部落篩選模式的 tribalStats 結構
// =========================================================

describe('Dashboard 部落篩選模式 tribalStats', function () {

    it('選擇部落時，tribalStats 含 by_food_category 與 by_processing_method', function () {
        $user = User::factory()->admin()->create();

        $fish = Fish::factory()->create();
        TribalClassification::factory()
            ->forTribe('ivalino')
            ->withFoodCategory('oyod')
            ->withProcessingMethod('去魚鱗')
            ->create(['fish_id' => $fish->id]);

        $response = $this->actingAs($user)->get('/dashboard?tribe=ivalino');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->has('tribalStats.by_food_category')
                ->has('tribalStats.by_processing_method')
                ->where('tribalStats.by_tribe', [])
        );
    });

    it('by_food_category 每項含 label 與 count', function () {
        $user = User::factory()->admin()->create();

        $fish = Fish::factory()->create();
        TribalClassification::factory()
            ->forTribe('ivalino')
            ->withFoodCategory('oyod')
            ->create(['fish_id' => $fish->id]);

        $response = $this->actingAs($user)->get('/dashboard?tribe=ivalino');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->has('tribalStats.by_food_category.0.label')
                ->has('tribalStats.by_food_category.0.count')
        );
    });

    it('by_processing_method 每項含 label 與 count', function () {
        $user = User::factory()->admin()->create();

        $fish = Fish::factory()->create();
        TribalClassification::factory()
            ->forTribe('ivalino')
            ->withProcessingMethod('去魚鱗')
            ->create(['fish_id' => $fish->id]);

        $response = $this->actingAs($user)->get('/dashboard?tribe=ivalino');

        $response->assertInertia(
            fn ($page) =>
            $page->component('Dashboard')
                ->has('tribalStats.by_processing_method.0.label')
                ->has('tribalStats.by_processing_method.0.count')
        );
    });
});
