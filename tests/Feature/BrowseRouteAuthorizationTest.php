<?php

use App\Models\Fish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function routeFor(string $method, string $uri): RoutingRoute
{
    return collect(Route::getRoutes())->first(
        fn (RoutingRoute $route) => in_array($method, $route->methods(), true)
            && $route->uri() === $uri
    );
}

it('keeps the intended web and API endpoints public', function () {
    $this->get('/')->assertOk();
    $this->getJson('/prefix/api/topics')->assertOk();
    $this->getJson('/prefix/api/health-check')->assertOk();
});

it('enforces browse access on a top-level web browsing route', function () {
    Http::fake(['*' => Http::response('', 404)]);

    $this->get('/fishs')->assertRedirect('/login');
    $this->actingAs(User::factory()->lineGuest()->create())->get('/fishs')->assertForbidden();
    $this->actingAs(User::factory()->lineViewer()->create())->get('/fishs')->assertOk();
});

it('enforces browse access on the existing authenticated web group', function () {
    $fish = Fish::factory()->create();
    $uri = "/fish/{$fish->id}/capture-records";

    $this->get($uri)->assertRedirect('/login');
    $this->actingAs(User::factory()->lineGuest()->create())->get($uri)->assertForbidden();
    $this->actingAs(User::factory()->lineViewer()->create())->get($uri)->assertOk();
});

it('enforces browse access on the fish detail route kept at the end', function () {
    $fish = Fish::factory()->create();
    $uri = "/fish/{$fish->id}";
    Http::fake(['*' => Http::response('', 404)]);

    $this->get($uri)->assertRedirect('/login');
    $this->actingAs(User::factory()->lineGuest()->create())->get($uri)->assertForbidden();
    $this->actingAs(User::factory()->lineViewer()->create())->get($uri)->assertOk();
});

it('enforces browse access on API read routes', function () {
    $this->getJson('/prefix/api/fish')->assertUnauthorized();
    $this->actingAs(User::factory()->lineGuest()->create())->getJson('/prefix/api/fish')->assertForbidden();
    $this->actingAs(User::factory()->lineViewer()->create())->getJson('/prefix/api/fish')->assertOk();
});

it('attaches browse middleware to every protected web route', function () {
    $protectedUris = [
        'fishs',
        'search',
        'topics/{slug}',
        'topics/{slug}/{itemId}',
        'fish/{id}',
    ];

    foreach ($protectedUris as $uri) {
        expect(routeFor('GET', $uri)->gatherMiddleware())
            ->toContain('auth')
            ->toContain('browse');
    }

    collect(Route::getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('auth', $route->gatherMiddleware(), true))
        ->reject(fn (RoutingRoute $route) => $route->uri() === 'prefix/api/user')
        ->each(fn (RoutingRoute $route) => expect($route->gatherMiddleware())->toContain('browse'));
});

it('requires browse access on every non-whitelisted GET API route', function () {
    $publicUris = [
        'prefix/api/user',
        'prefix/api/health-check',
        'prefix/api/topics',
    ];

    collect(Route::getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('GET', $route->methods(), true))
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'prefix/api/'))
        ->reject(fn (RoutingRoute $route) => in_array($route->uri(), $publicUris, true))
        ->each(function (RoutingRoute $route) {
            expect($route->gatherMiddleware())
                ->toContain('auth:sanctum')
                ->toContain('browse');
        });
});
