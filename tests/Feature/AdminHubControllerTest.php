<?php

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
