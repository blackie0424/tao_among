<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware('browse')->get('/test/browse-authorization', fn () => response()->noContent());
});

it('identifies which roles can browse', function (string $role, bool $expected) {
    $user = User::factory()->create(['role' => $role]);

    expect($user->canBrowse())->toBe($expected);
})->with([
    'guest cannot browse' => ['guest', false],
    'viewer can browse' => ['viewer', true],
    'editor can browse' => ['editor', true],
    'admin can browse' => ['admin', true],
]);

it('returns forbidden when browse middleware has no authenticated user', function () {
    $this->get('/test/browse-authorization')->assertForbidden();
});

it('returns forbidden when browse middleware receives a guest', function () {
    $this->actingAs(User::factory()->lineGuest()->create())
        ->get('/test/browse-authorization')
        ->assertForbidden();
});

it('allows roles with browse permission through the middleware', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/test/browse-authorization')
        ->assertNoContent();
})->with(['viewer', 'editor', 'admin']);
