<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Fish;
use App\Models\User;
use App\Models\TribalClassification;
use App\Models\CaptureRecord;

uses(RefreshDatabase::class);

// =========================================================
// 路由權限控制
// =========================================================

describe('FishReport 路由權限', function () {

    it('未登入應重導至登入頁', function () {
        $response = $this->get('/fish-report');
        $response->assertRedirect('/login');
    });

    it('一般登入使用者（非 admin）應被拒絕（403）', function () {
        $user = User::factory()->lineViewer()->create();
        $response = $this->actingAs($user)->get('/fish-report');
        $response->assertStatus(403);
    });

    it('admin 使用者可存取報告頁（200）', function () {
        $user = User::factory()->admin()->create();
        $response = $this->actingAs($user)->get('/fish-report');
        $response->assertStatus(200);
    });
});

// =========================================================
// Inertia Props 結構
// =========================================================

describe('FishReport Inertia props 結構', function () {

    it('回傳 FishReport 元件，包含所有必要 props', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->has('tribes')
                    ->has('foodCategories')
                    ->has('processingMethods')
                    ->has('statistics')
            );
    });

    it('statistics 包含 total_fish、food_categories_by_tribe、capture_methods_by_tribe 及 processing_methods', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->has('statistics.total_fish')
                    ->has('statistics.food_categories_by_tribe')
                    ->has('statistics.capture_methods_by_tribe')
                    ->has('statistics.processing_methods')
            );
    });

    it('tribes prop 包含所有 config 中定義的部落', function () {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->where('tribes', config('fish_options.tribes'))
            );
    });
});

// =========================================================
// 統計資料正確性
// =========================================================

describe('FishReport 統計資料正確性', function () {

    it('statistics.total_fish 反映實際魚種數量', function () {
        $user = User::factory()->admin()->create();
        Fish::factory()->count(4)->create();

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->where('statistics.total_fish', 4)
            );
    });

    it('statistics.food_categories_by_tribe 反映部落食用分類統計', function () {
        $user = User::factory()->admin()->create();
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();

        TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('oyod')->create(['fish_id' => $fish1->id]);
        TribalClassification::factory()->forTribe('ivalino')->withFoodCategory('oyod')->create(['fish_id' => $fish2->id]);
        TribalClassification::factory()->forTribe('iranmeilek')->withFoodCategory('rahet')->create(['fish_id' => $fish1->id]);

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->where('statistics.food_categories_by_tribe.ivalino.oyod', 2)
                    ->where('statistics.food_categories_by_tribe.iranmeilek.rahet', 1)
            );
    });

    it('statistics.capture_methods_by_tribe 反映部落捕獲方式統計', function () {
        $user = User::factory()->admin()->create();
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();

        CaptureRecord::factory()->forTribe('ivalino')->create(['fish_id' => $fish1->id, 'capture_method' => '網捕']);
        CaptureRecord::factory()->forTribe('ivalino')->create(['fish_id' => $fish2->id, 'capture_method' => '網捕']);
        CaptureRecord::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish1->id, 'capture_method' => '魚叉']);

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->where('statistics.capture_methods_by_tribe.ivalino.網捕', 2)
                    ->where('statistics.capture_methods_by_tribe.iranmeilek.魚叉', 1)
            );
    });

    it('statistics.processing_methods 反映各處理方式總數', function () {
        $user = User::factory()->admin()->create();
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();

        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish1->id, 'processing_method' => '去魚鱗']);
        TribalClassification::factory()->forTribe('ivalino')->create(['fish_id' => $fish2->id, 'processing_method' => '去魚鱗']);
        TribalClassification::factory()->forTribe('iranmeilek')->create(['fish_id' => $fish1->id, 'processing_method' => '剝皮']);

        $this->actingAs($user)->get('/fish-report')
            ->assertInertia(fn ($page) =>
                $page->component('FishReport')
                    ->where('statistics.processing_methods.去魚鱗', 2)
                    ->where('statistics.processing_methods.剝皮', 1)
            );
    });
});
