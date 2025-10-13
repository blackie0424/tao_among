<?php

use App\Models\Fish;
use App\Models\CaptureRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

describe('Capture Record Inertia Endpoints', function () {
    
    it('can view capture records page', function () {
        $fish = Fish::factory()->create(['name' => 'Test Fish']);
        $captureRecords = CaptureRecord::factory()->count(3)->create([
            'fish_id' => $fish->id
        ]);

        $response = $this->get("/fish/{$fish->id}/capture-records");

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                ->component('CaptureRecords')
                ->has('fish')
                ->where('fish.id', $fish->id)
                ->where('fish.name', 'Test Fish')
                ->has('fish.captureRecords', 3)
                ->has('tribes', 6)
            );
    });

    it('returns 404 for non-existent fish on capture records page', function () {
        $response = $this->get('/fish/99999/capture-records');

        $response->assertStatus(404);
    });

    it('can view create capture record page', function () {
        $fish = Fish::factory()->create(['name' => 'Test Fish']);

        $response = $this->get("/fish/{$fish->id}/capture-records/create");

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                ->component('CreateCaptureRecord')
                ->has('fish')
                ->where('fish.id', $fish->id)
                ->where('fish.name', 'Test Fish')
                ->has('tribes', 6)
            );
    });

    it('can store a capture record', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'image_filename' => 'test-capture-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Capture Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => 'Test capture notes'
        ];

        $response = $this->post("/fish/{$fish->id}/capture-records", $data);

        $response->assertRedirect("/fish/{$fish->id}/capture-records")
            ->assertSessionHas('success', '捕獲紀錄新增成功');

        $this->assertDatabaseHas('capture_records', [
            'fish_id' => $fish->id,
            'image_path' => 'test-capture-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Capture Location',
            'capture_method' => '網捕',
            'notes' => 'Test capture notes'
        ]);
        
        // Check the date separately since it's stored with time
        $record = CaptureRecord::where('fish_id', $fish->id)->first();
        expect($record->capture_date->format('Y-m-d'))->toBe('2024-01-15');
    });

    it('validates required fields when storing capture record', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'tribe' => 'iraraley',
            'location' => 'Test Location'
            // Missing required fields: image_filename, capture_method, capture_date
        ];

        $response = $this->post("/fish/{$fish->id}/capture-records", $data);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['image_filename', 'capture_method', 'capture_date']);
    });

    it('validates tribe field when storing capture record', function () {
        $fish = Fish::factory()->create();
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'invalid_tribe',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15'
        ];

        $response = $this->post("/fish/{$fish->id}/capture-records", $data);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['tribe']);
    });

    it('validates future date when storing capture record', function () {
        $fish = Fish::factory()->create();
        
        $futureDate = now()->addDays(1)->format('Y-m-d');
        
        $data = [
            'image_filename' => 'test-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => $futureDate
        ];

        $response = $this->post("/fish/{$fish->id}/capture-records", $data);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['capture_date']);
    });

    it('can view edit capture record page', function () {
        $fish = Fish::factory()->create(['name' => 'Test Fish']);
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'location' => 'Original Location'
        ]);

        $response = $this->get("/fish/{$fish->id}/capture-records/{$captureRecord->id}/edit");

        $response->assertStatus(200)
            ->assertInertia(
                fn (Assert $page) => $page
                ->component('EditCaptureRecord')
                ->has('fish')
                ->where('fish.id', $fish->id)
                ->has('record')
                ->where('record.id', $captureRecord->id)
                ->where('record.tribe', 'iraraley')
                ->where('record.location', 'Original Location')
                ->has('tribes', 6)
            );
    });

    it('returns 404 for non-existent capture record on edit page', function () {
        $fish = Fish::factory()->create();

        $response = $this->get("/fish/{$fish->id}/capture-records/99999/edit");

        $response->assertStatus(404);
    });

    it('returns 404 for capture record not belonging to fish on edit page', function () {
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish2->id
        ]);

        $response = $this->get("/fish/{$fish1->id}/capture-records/{$captureRecord->id}/edit");

        $response->assertStatus(404);
    });

    it('can update a capture record', function () {
        $fish = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'tribe' => 'iraraley',
            'location' => 'Original Location'
        ]);

        $updateData = [
            'tribe' => 'imowrod',
            'location' => 'Updated Location',
            'capture_method' => '釣魚',
            'capture_date' => '2024-02-15',
            'notes' => 'Updated notes'
        ];

        $response = $this->put("/fish/{$fish->id}/capture-records/{$captureRecord->id}", $updateData);

        $response->assertRedirect("/fish/{$fish->id}/capture-records");

        $this->assertDatabaseHas('capture_records', [
            'id' => $captureRecord->id,
            'tribe' => 'imowrod',
            'location' => 'Updated Location',
            'capture_method' => '釣魚',
            'notes' => 'Updated notes'
        ]);
        
        // Check the date separately since it's stored with time
        $updatedRecord = CaptureRecord::find($captureRecord->id);
        expect($updatedRecord->capture_date->format('Y-m-d'))->toBe('2024-02-15');
    });

    it('can update capture record with new image', function () {
        $fish = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id,
            'image_path' => 'old-image.jpg'
        ]);

        $updateData = [
            'image_filename' => 'new-image.jpg',
            'tribe' => 'iraraley',
            'location' => 'Test Location',
            'capture_method' => '網捕',
            'capture_date' => '2024-01-15',
            'notes' => 'Updated with new image'
        ];

        $response = $this->put("/fish/{$fish->id}/capture-records/{$captureRecord->id}", $updateData);

        $response->assertRedirect("/fish/{$fish->id}/capture-records");

        $this->assertDatabaseHas('capture_records', [
            'id' => $captureRecord->id,
            'image_path' => 'new-image.jpg'
        ]);
    });

    it('validates fields when updating capture record', function () {
        $fish = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id
        ]);

        $updateData = [
            'tribe' => 'invalid_tribe',
            'location' => '',
            'capture_method' => '',
            'capture_date' => 'invalid-date'
        ];

        $response = $this->put("/fish/{$fish->id}/capture-records/{$captureRecord->id}", $updateData);

        $response->assertStatus(302)
            ->assertSessionHasErrors(['tribe', 'location', 'capture_method', 'capture_date']);
    });

    it('can delete a capture record', function () {
        $fish = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish->id
        ]);

        $response = $this->delete("/fish/{$fish->id}/capture-records/{$captureRecord->id}");

        $response->assertRedirect("/fish/{$fish->id}/capture-records")
            ->assertSessionHas('success', '捕獲紀錄刪除成功');

        $this->assertSoftDeleted('capture_records', [
            'id' => $captureRecord->id
        ]);
    });

    it('returns 404 when deleting non-existent capture record', function () {
        $fish = Fish::factory()->create();

        $response = $this->delete("/fish/{$fish->id}/capture-records/99999");

        $response->assertStatus(404);
    });

    it('returns 404 when deleting capture record not belonging to fish', function () {
        $fish1 = Fish::factory()->create();
        $fish2 = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create([
            'fish_id' => $fish2->id
        ]);

        $response = $this->delete("/fish/{$fish1->id}/capture-records/{$captureRecord->id}");

        $response->assertStatus(404);
    });

    it('includes tribes options in all pages', function () {
        $fish = Fish::factory()->create();
        $captureRecord = CaptureRecord::factory()->create(['fish_id' => $fish->id]);

        $expectedTribes = ['ivalino', 'iranmeilek', 'imowrod', 'iratay', 'yayo', 'iraraley'];

        // Test capture records index page
        $response = $this->get("/fish/{$fish->id}/capture-records");
        $response->assertInertia(
            fn (Assert $page) => $page
            ->where('tribes', $expectedTribes)
        );

        // Test create page
        $response = $this->get("/fish/{$fish->id}/capture-records/create");
        $response->assertInertia(
            fn (Assert $page) => $page
            ->where('tribes', $expectedTribes)
        );

        // Test edit page
        $response = $this->get("/fish/{$fish->id}/capture-records/{$captureRecord->id}/edit");
        $response->assertInertia(
            fn (Assert $page) => $page
            ->where('tribes', $expectedTribes)
        );
    });
});
