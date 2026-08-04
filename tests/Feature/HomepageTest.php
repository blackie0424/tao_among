<?php

use App\Models\IntroSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('首頁只傳遞已發布的投影片給 Index', function () {
    IntroSlide::factory()->create(['is_published' => true, 'sort_order' => 1, 'title' => '已發布']);
    IntroSlide::factory()->create(['is_published' => false, 'title' => '草稿']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Index')
            ->has('slides', 1)
            ->where('slides.0.title', '已發布')
        );
});

it('首頁無已發布投影片時 slides 為空陣列', function () {
    IntroSlide::factory()->create(['is_published' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Index')
            ->has('slides', 0)
        );
});

it('首頁投影片依 sort_order 排序', function () {
    IntroSlide::factory()->create(['is_published' => true, 'sort_order' => 2, 'title' => '第二張']);
    IntroSlide::factory()->create(['is_published' => true, 'sort_order' => 1, 'title' => '第一張']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('slides.0.title', '第一張')
            ->where('slides.1.title', '第二張')
        );
});
