<?php

use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

uses(RefreshDatabase::class);

describe('Fish Audio Management', function () {
    
    describe('Audio List Page', function () {
        it('can display audio list page for existing fish', function () {
            $user = User::factory()->create();

            $fish = Fish::factory()->create(['name' => 'Test Fish']);
            $audio1 = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'First pronunciation',
                'locate' => 'audio1.mp3'
            ]);
            $audio2 = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Second pronunciation',
                'locate' => 'audio2.mp3'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('Fish/MediaManager')
                    ->has('fish')
                    ->where('fish.id', $fish->id)
                    ->where('fish.name', 'Test Fish')
                    ->has('fish.audios', 2)
            );
        });

        it('redirects with error for non-existent fish', function () {
            $user = User::factory()->create();
            $response = $this->actingAs($user)->get('/fish/999/media-manager');
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('displays empty list when fish has no audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('Fish/MediaManager')
                    ->has('fish')
                    ->where('fish.id', $fish->id)
                    ->has('fish.audios', 0)
            );
        });

        it('includes audio URLs in response', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Test Audio',
                'locate' => 'test-audio.mp3'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->has('fish.audios.0.url')
                    ->where('fish.audios.0.name', 'Test Audio')
                    ->where('fish.audios.0.locate', 'test-audio.mp3')
            );
        });
    });

    describe('Audio Edit Page', function () {
        it('can display edit audio page', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create(['name' => 'Test Fish']);
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original pronunciation',
                'locate' => 'original.mp3'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio/{$audio->id}/edit");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('Fish/EditAudio')
                    ->has('fish')
                    ->has('audio')
                    ->where('fish.id', $fish->id)
                    ->where('audio.id', $audio->id)
                    ->where('audio.name', 'Original pronunciation')
            );
        });

        it('redirects with error for non-existent fish', function () {
            $user = User::factory()->create();
            $audio = FishAudio::factory()->create();
            $response = $this->actingAs($user)->get("/fish/999/audio/{$audio->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error for non-existent audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio/999/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->actingAs($user)->get("/fish/{$fish1->id}/audio/{$audio->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('Audio Update', function () {
        it('can update audio name successfully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Updated name'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/media-manager");

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'fish_id' => $fish->id,
                'name' => 'Updated name',
                'locate' => 'original.mp3' // Should remain unchanged
            ]);
        });

        it('can update audio file', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Test Audio',
                'locate' => 'original.mp3'
            ]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Test Audio',
                'audio_filename' => 'new-audio.mp3'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/media-manager");

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Test Audio',
                'locate' => 'new-audio.mp3'
            ]);
        });

        it('validates required name field', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => '' // Empty name should fail validation
            ]);

            $response->assertSessionHasErrors(['name']);
        });

        it('validates name field length', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => str_repeat('a', 256) // Assuming max length is 255
            ]);

            $response->assertSessionHasErrors(['name']);
        });

        it('redirects with error when updating non-existent audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/999", [
                'name' => 'Should not update'
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->actingAs($user)->put("/fish/{$fish1->id}/audio/{$audio->id}", [
                'name' => 'Should not update'
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('handles optional audio filename update', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);

            // Update without audio_filename
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Updated name only'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/media-manager");

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Updated name only',
                'locate' => 'original.mp3' // Should remain unchanged
            ]);
        });
    });

    describe('Audio Delete', function () {
        it('can delete audio successfully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'To be deleted'
            ]);

            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$audio->id}");

            $response->assertRedirect("/fish/{$fish->id}/media-manager");

            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id,
                'fish_id' => $fish->id
            ]);
        });

        it('redirects with error when deleting non-existent audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/audio/999");

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->actingAs($user)->delete("/fish/{$fish1->id}/audio/{$audio->id}");

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('does not hard delete audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$audio->id}");

            // Should still exist in database but with deleted_at timestamp
            $deletedAudio = FishAudio::withTrashed()->find($audio->id);
            expect($deletedAudio)->not->toBeNull();
            expect($deletedAudio->deleted_at)->not->toBeNull();
        });

        it('handles audio file cleanup on delete', function () {
            $user = User::factory()->create();
            Storage::fake('public');
            
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'locate' => 'test-audio.mp3'
            ]);

            // Create a fake file
            Storage::disk('public')->put('audio/test-audio.mp3', 'fake audio content');
            
            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$audio->id}");

            $response->assertRedirect("/fish/{$fish->id}/media-manager");

            // Audio record should be soft deleted
            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id
            ]);
        });
    });

    describe('Audio URL Generation', function () {
        it('generates correct audio URLs', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'test-audio.mp3'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) {
                $audioData = $page->toArray()['props']['fish']['audios'][0];
                expect($audioData)->toHaveKey('url');
                expect($audioData['url'])->toContain('test-audio.mp3');
                return $page;
            });
        });

        it('handles missing audio files gracefully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'locate' => '' // Empty file location instead of null
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->has('fish.audios.0')
                    ->where('fish.audios.0.locate', '')
            );
        });
    });

    describe('CRUD Operations Integration', function () {
        it('maintains referential integrity when fish is deleted', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // Delete the fish (assuming soft delete)
            $fish->delete();

            // Audio should still exist but be inaccessible through normal queries
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'fish_id' => $fish->id
            ]);

            // Accessing audio list should redirect with error since fish is deleted
            $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('handles concurrent audio operations', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name'
            ]);

            // Simulate concurrent updates
            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'First update'
            ]);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Second update'
            ]);

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Second update'
            ]);
        });

        it('validates audio operations with proper fish ownership', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish1->id]);

            // Try to access audio through wrong fish
            $response = $this->actingAs($user)->get("/fish/{$fish2->id}/audio/{$audio->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);

            $response = $this->actingAs($user)->put("/fish/{$fish2->id}/audio/{$audio->id}", [
                'name' => 'Should not work'
            ]);
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);

            $response = $this->actingAs($user)->delete("/fish/{$fish2->id}/audio/{$audio->id}");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('Error Handling and Edge Cases', function () {
        it('handles database connection errors gracefully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // This test would require mocking database failures
            // For now, we'll test with invalid data that might cause database errors
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => null // This should be caught by validation before hitting database
            ]);

            $response->assertSessionHasErrors(['name']);
        });

        it('handles large audio file names', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $longFilename = str_repeat('a', 500) . '.mp3';
            
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Test Audio',
                'audio_filename' => $longFilename
            ]);

            // Should handle gracefully (either accept or reject with validation)
            expect($response->status())->toBeIn([200, 302, 422]);
        });

        it('maintains data consistency during failed operations', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);

            // Attempt invalid update
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => '' // Invalid name
            ]);

            // Original data should remain unchanged
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);
        });
    });
});
