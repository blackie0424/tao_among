<?php

use App\Models\Reference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
});

it('reference index requires authentication', function () {
    $response = $this->get('/admin/references');

    $response->assertRedirect('/login');
});

it('reference index requires admin role', function () {
    $response = $this->actingAs($this->editor)->get('/admin/references');

    $response->assertStatus(403);
});

it('admin can view reference index page', function () {
    Reference::factory()->count(2)->create();

    $response = $this->actingAs($this->admin)->get('/admin/references');

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('Admin/References/Index')
            ->has('references.data', 2)
    );
});

it('admin can create a reference', function () {
    $response = $this->actingAs($this->admin)->post('/admin/references', [
        'name' => '達悟族植物利用調查',
        'image_url' => 'https://example.com/book.jpg',
        'external_url' => 'https://example.com/book',
        'author' => '王小明',
        'status' => 'enabled',
    ]);

    $response->assertRedirect('/admin/references');

    $this->assertDatabaseHas('references', [
        'name' => '達悟族植物利用調查',
        'image_url' => 'https://example.com/book.jpg',
        'external_url' => 'https://example.com/book',
        'author' => '王小明',
        'status' => 'enabled',
    ]);
});

it('admin can update a reference', function () {
    $reference = Reference::factory()->create([
        'status' => 'enabled',
    ]);

    $response = $this->actingAs($this->admin)->put("/admin/references/{$reference->id}", [
        'name' => '更新後文獻',
        'image_url' => 'https://example.com/new-book.jpg',
        'external_url' => 'https://example.com/new-book',
        'author' => '李小華',
        'status' => 'disabled',
    ]);

    $response->assertRedirect('/admin/references');

    $this->assertDatabaseHas('references', [
        'id' => $reference->id,
        'name' => '更新後文獻',
        'image_url' => 'https://example.com/new-book.jpg',
        'external_url' => 'https://example.com/new-book',
        'author' => '李小華',
        'status' => 'disabled',
    ]);
});

