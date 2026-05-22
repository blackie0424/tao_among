<?php

use App\Models\Fish;
use App\Models\Reference;
use App\Models\ReferenceKnowledge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->editor = User::factory()->lineEditor()->create();
    $this->viewer = User::factory()->lineViewer()->create();
});

it('reference knowledge pages require authentication', function () {
    $fish = Fish::factory()->create();

    $response = $this->get("/fish/{$fish->id}/reference-knowledge");

    $response->assertRedirect('/login');
});

it('editor can view reference knowledge index page', function () {
    $fish = Fish::factory()->create();
    $reference = Reference::factory()->enabled()->create();
    ReferenceKnowledge::factory()->create([
        'fish_id' => $fish->id,
        'reference_id' => $reference->id,
    ]);

    $response = $this->actingAs($this->editor)->get("/fish/{$fish->id}/reference-knowledge");

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('ReferenceKnowledge/Index')
            ->where('fish.id', $fish->id)
            ->has('knowledge.data', 1)
    );
});

it('editor can load reference knowledge create page with tribe options', function () {
    $fish = Fish::factory()->create();
    Reference::factory()->enabled()->create();

    $response = $this->actingAs($this->editor)->get("/fish/{$fish->id}/reference-knowledge/create");

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('ReferenceKnowledge/Create')
            ->where('fish.id', $fish->id)
            ->where('tribes', config('fish_options.tribes'))
    );
});

it('viewer cannot access reference knowledge pages', function () {
    $fish = Fish::factory()->create();

    $response = $this->actingAs($this->viewer)->get("/fish/{$fish->id}/reference-knowledge");

    $response->assertStatus(403);
});

it('admin can create reference knowledge for a fish', function () {
    $fish = Fish::factory()->create();
    $reference = Reference::factory()->enabled()->create();

    $response = $this->actingAs($this->admin)->post("/fish/{$fish->id}/reference-knowledge", [
        'reference_id' => $reference->id,
        'content' => '此魚可作為重要祭儀食材。',
        'pages' => '12-15',
        'note' => '摘錄整理',
        'tribe' => 'iraraley',
    ]);

    $response->assertRedirect("/fish/{$fish->id}/reference-knowledge");

    $this->assertDatabaseHas('reference_knowledge', [
        'fish_id' => $fish->id,
        'reference_id' => $reference->id,
        'content' => '此魚可作為重要祭儀食材。',
        'pages' => '12-15',
        'note' => '摘錄整理',
        'tribe' => 'iraraley',
        'page_start' => 12,
        'page_end' => 15,
        'created_by' => $this->admin->id,
    ]);
});

it('editor can update reference knowledge', function () {
    $fish = Fish::factory()->create();
    $reference = Reference::factory()->enabled()->create();
    $knowledge = ReferenceKnowledge::factory()->create([
        'fish_id' => $fish->id,
        'reference_id' => $reference->id,
        'tribe' => 'ivalino',
    ]);

    $response = $this->actingAs($this->editor)->put("/fish/{$fish->id}/reference-knowledge/{$knowledge->id}", [
        'reference_id' => $reference->id,
        'content' => '更新後的文獻內容',
        'pages' => '33-36',
        'note' => '更新備註',
        'tribe' => null,
    ]);

    $response->assertRedirect("/fish/{$fish->id}/reference-knowledge");

    $this->assertDatabaseHas('reference_knowledge', [
        'id' => $knowledge->id,
        'content' => '更新後的文獻內容',
        'pages' => '33-36',
        'note' => '更新備註',
        'tribe' => null,
        'page_start' => 33,
        'page_end' => 36,
    ]);
});

it('editor can delete reference knowledge', function () {
    $fish = Fish::factory()->create();
    $knowledge = ReferenceKnowledge::factory()->create([
        'fish_id' => $fish->id,
    ]);

    $response = $this->actingAs($this->editor)->delete("/fish/{$fish->id}/reference-knowledge/{$knowledge->id}");

    $response->assertRedirect("/fish/{$fish->id}/reference-knowledge");

    $this->assertSoftDeleted('reference_knowledge', [
        'id' => $knowledge->id,
    ]);
});

it('cannot create reference knowledge with disabled reference', function () {
    $fish = Fish::factory()->create();
    $reference = Reference::factory()->disabled()->create();

    $response = $this->from("/fish/{$fish->id}/reference-knowledge/create")
        ->actingAs($this->editor)
        ->post("/fish/{$fish->id}/reference-knowledge", [
            'reference_id' => $reference->id,
            'content' => '這筆資料不應建立',
            'pages' => '10',
            'note' => null,
        ]);

    $response->assertRedirect("/fish/{$fish->id}/reference-knowledge/create");
    $response->assertSessionHasErrors(['reference_id']);

    $this->assertDatabaseMissing('reference_knowledge', [
        'fish_id' => $fish->id,
        'reference_id' => $reference->id,
        'content' => '這筆資料不應建立',
    ]);
});

it('reference knowledge index orders entries by reference then page range', function () {
    $fish = Fish::factory()->create();
    $alphaReference = Reference::factory()->enabled()->create(['name' => '甲書']);
    $betaReference = Reference::factory()->enabled()->create(['name' => '乙書']);

    $laterPage = ReferenceKnowledge::factory()->create([
        'fish_id' => $fish->id,
        'reference_id' => $alphaReference->id,
        'pages' => '16',
        'page_start' => 16,
        'page_end' => 16,
    ]);

    $firstPage = ReferenceKnowledge::factory()->create([
        'fish_id' => $fish->id,
        'reference_id' => $alphaReference->id,
        'pages' => '12-15',
        'page_start' => 12,
        'page_end' => 15,
    ]);

    $otherReference = ReferenceKnowledge::factory()->create([
        'fish_id' => $fish->id,
        'reference_id' => $betaReference->id,
        'pages' => '5',
        'page_start' => 5,
        'page_end' => 5,
    ]);

    $response = $this->actingAs($this->editor)->get("/fish/{$fish->id}/reference-knowledge");

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page
            ->component('ReferenceKnowledge/Index')
            ->where('knowledge.data.0.id', $firstPage->id)
            ->where('knowledge.data.1.id', $laterPage->id)
            ->where('knowledge.data.2.id', $otherReference->id)
    );
});
