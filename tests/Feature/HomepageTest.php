<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('首頁不再傳遞投影片資料', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Index')
            ->missing('slides')
        );
});

it('不再註冊投影片與投影片分類管理路由', function () {
    $routeNames = [
        'admin.intro-categories.index',
        'admin.intro-categories.create',
        'admin.intro-categories.store',
        'admin.intro-categories.edit',
        'admin.intro-categories.update',
        'admin.intro-categories.destroy',
        'admin.intro-slides.index',
        'admin.intro-slides.create',
        'admin.intro-slides.store',
        'admin.intro-slides.edit',
        'admin.intro-slides.update',
        'admin.intro-slides.destroy',
        'admin.intro-slides.toggle-published',
    ];

    foreach ($routeNames as $routeName) {
        expect(Route::getRoutes()->getByName($routeName))->toBeNull();
    }
});
