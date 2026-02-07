<?php

use App\Models\Fish;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Http;
use App\Models\User;

uses(RefreshDatabase::class);

it('renders homepage with Index component', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->component('Index'));
});

it('renders fish list with expected props', function () {
    // 兩筆資料：一筆無音檔，一筆有音檔
    Fish::factory()->create(['audio_filename' => null]);
    Fish::factory()->create(['audio_filename' => 'voice.mp3']);

    // 偽造外部 HEAD 請求，避免實際網路 I/O
    Http::fake(['*' => Http::response('', 404)]);

    $response = $this->get('/fishs');

    $response->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
            ->component('Fishs')
            ->has('items')
            ->has('pageInfo')
            ->has('filters')
            ->has('searchOptions')
            ->has('searchStats')
        );
});

it('renders search page with expected props', function () {
    Fish::factory()->count(1)->create(['audio_filename' => null]);

    Http::fake(['*' => Http::response('', 404)]);

    $response = $this->get('/search?name=Test');

    $response->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
            ->component('Fish/Search')
            ->has('fishs')
            ->has('filters')
            ->has('searchOptions')
            ->has('searchStats')
            ->where('fishs.0.image', fn ($v) => is_string($v) && $v !== '')
        );
});

it('renders fish detail page with grouped notes and relations', function () {
    $fish = Fish::factory()->create();
    // 偽造 Supabase HEAD 請求避免實際網路呼叫（回 404 讓服務回退到原檔連結）
    Http::fake([
        '*' => Http::response('', 404),
    ]);

    $response = $this->get("/fish/{$fish->id}");

    $response->assertStatus(200)
        ->assertInertia(
            fn (Assert $page) => $page
            ->component('Fish')
            ->has('fish')
            ->has('tribalClassifications')
            ->has('captureRecords')
            ->has('fishNotes')
        );
});

it('returns 404 for non-existent fish detail', function () {
    $response = $this->get('/fish/999999');
    $response->assertStatus(404);
});

it('creates a new fish and returns capture prompt props', function () {
    $user = User::factory()->create();
    $fishData = [
        'name' => 'Test Fish',
        'image' => 'test-fish-123.jpg',
    ];

    $response = $this->actingAs($user)->post('/fish', $fishData);

    // 控制器會 redirect 到魚類詳情頁
    $response->assertStatus(302);
    
    // 驗證資料庫中有建立魚類
    $fish = Fish::where('name', 'Test Fish')->where('image', 'test-fish-123.jpg')->first();
    expect($fish)->not->toBeNull();
    
    // 驗證重定向到正確的魚類詳情頁
    $response->assertRedirect("/fish/{$fish->id}");
    
    // 驗證有 success flash message
    $response->assertSessionHas('success');
});

it('creates fish without audio_filename and still returns capture prompt', function () {
    $user = User::factory()->create();
    $fishData = [
        'name' => 'Another Fish',
        'image' => 'another-fish-456.jpg',
    ];

    $response = $this->actingAs($user)->post('/fish', $fishData);

    // 控制器會 redirect 到魚類詳情頁
    $response->assertStatus(302);
    
    // 驗證資料庫中有建立魚類
    $fish = Fish::where('name', 'Another Fish')->where('image', 'another-fish-456.jpg')->first();
    expect($fish)->not->toBeNull();
    
    // 驗證重定向到正確的魚類詳情頁
    $response->assertRedirect("/fish/{$fish->id}");
    
    // 驗證有 success flash message
    $response->assertSessionHas('success');
});

it('fails to create fish without required name', function () {
    $user = User::factory()->create();
    $fishData = [
        'image' => 'test-fish.jpg',
    ];

    $response = $this->actingAs($user)->post('/fish', $fishData);

    $response->assertStatus(302); // Redirect back with errors
    $response->assertSessionHasErrors('name');
});

it('fails to create fish without required image', function () {
    $user =User::factory()->create();
    $fishData = [
        'name' => 'Test Fish',
    ];

    $response = $this->actingAs($user)->post('/fish', $fishData);

    $response->assertStatus(302); // Redirect back with errors
    $response->assertSessionHasErrors('image');
});
