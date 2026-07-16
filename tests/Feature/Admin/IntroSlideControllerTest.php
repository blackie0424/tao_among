<?php

use App\Models\IntroCategory;
use App\Models\IntroSlide;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
});

// --- 權限 ---

it('未登入者無法存取 intro-slides', function () {
    $this->get('/admin/intro-slides')->assertRedirect('/login');
});

it('editor 無法存取 intro-slides', function () {
    $this->actingAs($this->editor)->get('/admin/intro-slides')->assertStatus(403);
});

// --- Index ---

it('admin 可以瀏覽 intro-slides 列表', function () {
    IntroSlide::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get('/admin/intro-slides')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IntroSlides/Index')
            ->has('slides.data', 2)
        );
});

// --- Create ---

it('admin 可以瀏覽 intro-slide 新增頁面', function () {
    IntroCategory::factory()->count(2)->create();

    $this->actingAs($this->admin)
        ->get('/admin/intro-slides/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IntroSlides/Create')
            ->has('categories', 2)
        );
});

// --- Store (YouTube) ---

it('admin 可以新增 youtube 類型的 intro-slide', function () {
    $this->actingAs($this->admin)
        ->post('/admin/intro-slides', [
            'title' => '蘭嶼簡介',
            'body' => '介紹蘭嶼的影片',
            'media_type' => 'youtube',
            'media_path' => 'https://www.youtube.com/watch?v=abc123',
            'sort_order' => 1,
            'is_published' => true,
        ])
        ->assertRedirect('/admin/intro-slides');

    $this->assertDatabaseHas('intro_slides', [
        'title' => '蘭嶼簡介',
        'media_type' => 'youtube',
        'is_published' => true,
    ]);
});

// --- Store (Photo) ---

it('admin 可以上傳圖片並新增 photo 類型的 intro-slide', function () {
    Storage::fake('public');
    Storage::fake('s3');

    $file = UploadedFile::fake()->image('slide.jpg');

    $this->actingAs($this->admin)
        ->post('/admin/intro-slides', [
            'title' => '圖片投影片',
            'media_type' => 'photo',
            'photo' => $file,
            'sort_order' => 2,
            'is_published' => false,
        ])
        ->assertRedirect('/admin/intro-slides');

    $slide = IntroSlide::where('title', '圖片投影片')->first();
    expect($slide)->not->toBeNull();
    expect($slide->media_path)->toStartWith('intro-slides/');
});

it('store 驗證：title 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/intro-slides', [
            'media_type' => 'youtube',
            'media_path' => 'https://youtube.com/watch?v=x',
        ])
        ->assertSessionHasErrors('title');
});

it('store 驗證：media_type 必填', function () {
    $this->actingAs($this->admin)
        ->post('/admin/intro-slides', ['title' => '測試'])
        ->assertSessionHasErrors('media_type');
});

// --- Edit ---

it('admin 可以瀏覽 intro-slide 編輯頁面', function () {
    $slide = IntroSlide::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/intro-slides/{$slide->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/IntroSlides/Edit')
            ->where('slide.id', $slide->id)
        );
});

// --- Update ---

it('admin 可以更新 intro-slide', function () {
    $slide = IntroSlide::factory()->create(['title' => '舊標題', 'media_type' => 'youtube', 'media_path' => 'https://youtube.com/x']);

    $this->actingAs($this->admin)
        ->put("/admin/intro-slides/{$slide->id}", [
            'title' => '新標題',
            'media_type' => 'youtube',
            'media_path' => 'https://youtube.com/y',
            'sort_order' => 3,
            'is_published' => true,
        ])
        ->assertRedirect('/admin/intro-slides');

    $this->assertDatabaseHas('intro_slides', ['id' => $slide->id, 'title' => '新標題', 'is_published' => true]);
});

// --- Destroy ---

it('admin 可以刪除 intro-slide', function () {
    $slide = IntroSlide::factory()->create();

    $this->actingAs($this->admin)
        ->delete("/admin/intro-slides/{$slide->id}")
        ->assertRedirect('/admin/intro-slides');

    $this->assertDatabaseMissing('intro_slides', ['id' => $slide->id]);
});

// --- Toggle Published ---

it('admin 可以切換 intro-slide 發布狀態', function () {
    $slide = IntroSlide::factory()->create(['is_published' => false]);

    $this->actingAs($this->admin)
        ->patch("/admin/intro-slides/{$slide->id}/toggle-published")
        ->assertRedirect('/admin/intro-slides');

    $this->assertDatabaseHas('intro_slides', ['id' => $slide->id, 'is_published' => true]);
});
