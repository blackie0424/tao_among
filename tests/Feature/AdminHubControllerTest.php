<?php

use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('counts guest users as pending approval', function () {
    User::factory()->lineGuest()->count(2)->create();
    User::factory()->lineViewer()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Hub')
            ->where('pendingUsers', 2)
        );
});

it('returns the total topic count', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Hub')
            ->where('stats.topicCount', Topic::count())
        );
});

it('returns zero when there are no topics', function () {
    Topic::query()->delete();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Hub')
            ->where('stats.topicCount', 0)
        );
});
