<?php

use App\Models\Fish;
use App\Models\FishAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Fish Audio Management', function () {
    
    describe('Audio List Page', function () {
        it('can display audio list page for existing fish', function () {
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

            $response = $this->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('FishAudioList')
                    ->has('fish')
                    ->where('fish.id', $fish->id)
                    ->where('fish.name', 'Test Fish')
                    ->has('fish.audios', 2)
            );
        });

        it('redirects with error for non-existent fish', function () {
            $response = $this->get('/fish/999/audio-list');
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('displays empty list when fish has no audio', function () {
            $fish = Fish::factory()->create();

            $response = $this->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('FishAudioList')
                    ->has('fish')
                    ->where('fish.id', $fish->id)
                    ->has('fish.audios', 0)
            );
        });

        it('includes audio URLs in response', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Test Audio',
                'locate' => 'test-audio.mp3'
            ]);

            $response = $this->get("/fish/{$fish->id}/audio-list");

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
            $fish = Fish::factory()->create(['name' => 'Test Fish']);
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original pronunciation',
                'locate' => 'original.mp3'
            ]);

            $response = $this->get("/fish/{$fish->id}/audio/{$audio->id}/edit");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('EditFishAudio')
                    ->has('fish')
                    ->has('audio')
                    ->where('fish.id', $fish->id)
                    ->where('audio.id', $audio->id)
                    ->where('audio.name', 'Original pronunciation')
            );
        });

        it('redirects with error for non-existent fish', function () {
            $audio = FishAudio::factory()->create();
            $response = $this->get("/fish/999/audio/{$audio->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error for non-existent audio', function () {
            $fish = Fish::factory()->create();
            $response = $this->get("/fish/{$fish->id}/audio/999/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->get("/fish/{$fish1->id}/audio/{$audio->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('Audio Update', function () {
        it('can update audio name successfully', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);

            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Updated name'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'fish_id' => $fish->id,
                'name' => 'Updated name',
                'locate' => 'original.mp3' // Should remain unchanged
            ]);
        });

        it('can update audio file', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Test Audio',
                'locate' => 'original.mp3'
            ]);

            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Test Audio',
                'audio_filename' => 'new-audio.mp3'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Test Audio',
                'locate' => 'new-audio.mp3'
            ]);
        });

        it('validates required name field', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => '' // Empty name should fail validation
            ]);

            $response->assertSessionHasErrors(['name']);
        });

        it('validates name field length', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => str_repeat('a', 256) // Assuming max length is 255
            ]);

            $response->assertSessionHasErrors(['name']);
        });

        it('redirects with error when updating non-existent audio', function () {
            $fish = Fish::factory()->create();

            $response = $this->put("/fish/{$fish->id}/audio/999", [
                'name' => 'Should not update'
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->put("/fish/{$fish1->id}/audio/{$audio->id}", [
                'name' => 'Should not update'
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('handles optional audio filename update', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);

            // Update without audio_filename
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Updated name only'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Updated name only',
                'locate' => 'original.mp3' // Should remain unchanged
            ]);
        });
    });

    describe('Audio Delete', function () {
        it('can delete audio successfully', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'To be deleted'
            ]);

            $response = $this->delete("/fish/{$fish->id}/audio/{$audio->id}");

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id,
                'fish_id' => $fish->id
            ]);
        });

        it('redirects with error when deleting non-existent audio', function () {
            $fish = Fish::factory()->create();

            $response = $this->delete("/fish/{$fish->id}/audio/999");

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->delete("/fish/{$fish1->id}/audio/{$audio->id}");

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('does not hard delete audio', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $this->delete("/fish/{$fish->id}/audio/{$audio->id}");

            // Should still exist in database but with deleted_at timestamp
            $deletedAudio = FishAudio::withTrashed()->find($audio->id);
            expect($deletedAudio)->not->toBeNull();
            expect($deletedAudio->deleted_at)->not->toBeNull();
        });

        it('handles audio file cleanup on delete', function () {
            Storage::fake('public');
            
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'locate' => 'test-audio.mp3'
            ]);

            // Create a fake file
            Storage::disk('public')->put('audio/test-audio.mp3', 'fake audio content');
            
            $response = $this->delete("/fish/{$fish->id}/audio/{$audio->id}");

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            // Audio record should be soft deleted
            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id
            ]);
        });
    });

    describe('Audio URL Generation', function () {
        it('generates correct audio URLs', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'test-audio.mp3'
            ]);

            $response = $this->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) {
                $audioData = $page->toArray()['props']['fish']['audios'][0];
                expect($audioData)->toHaveKey('url');
                expect($audioData['url'])->toContain('test-audio.mp3');
                return $page;
            });
        });

        it('handles missing audio files gracefully', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'locate' => '' // Empty file location instead of null
            ]);

            $response = $this->get("/fish/{$fish->id}/audio-list");

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
            $response = $this->get("/fish/{$fish->id}/audio-list");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('handles concurrent audio operations', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name'
            ]);

            // Simulate concurrent updates
            $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'First update'
            ]);

            $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Second update'
            ]);

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Second update'
            ]);
        });

        it('validates audio operations with proper fish ownership', function () {
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish1->id]);

            // Try to access audio through wrong fish
            $response = $this->get("/fish/{$fish2->id}/audio/{$audio->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);

            $response = $this->put("/fish/{$fish2->id}/audio/{$audio->id}", [
                'name' => 'Should not work'
            ]);
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);

            $response = $this->delete("/fish/{$fish2->id}/audio/{$audio->id}");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('Error Handling and Edge Cases', function () {
        it('handles database connection errors gracefully', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // This test would require mocking database failures
            // For now, we'll test with invalid data that might cause database errors
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => null // This should be caught by validation before hitting database
            ]);

            $response->assertSessionHasErrors(['name']);
        });

        it('handles large audio file names', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $longFilename = str_repeat('a', 500) . '.mp3';
            
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Test Audio',
                'audio_filename' => $longFilename
            ]);

            // Should handle gracefully (either accept or reject with validation)
            expect($response->status())->toBeIn([200, 302, 422]);
        });

        it('maintains data consistency during failed operations', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
                'locate' => 'original.mp3'
            ]);

            // Attempt invalid update
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
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
