<?php

use App\Models\Fish;
use App\Models\FishNote;
use App\Models\FishAudio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Fish Knowledge and Audio Management Integration', function () {
    
    describe('Complete Navigation Flow', function () {
        it('can navigate from fish detail to knowledge list and back', function () {
            $fish = Fish::factory()->create(['name' => 'Integration Test Fish']);
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Test knowledge',
                'note_type' => '生態習性'
            ]);

            // Start from fish detail page (simulated)
            // Navigate to knowledge list
            $response = $this->get("/fish/{$fish->id}/knowledge-list");
            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('FishKnowledgeList')
                    ->where('fish.id', $fish->id)
                    ->has('groupedNotes')
            );

            // Navigate to edit knowledge
            $response = $this->get("/fish/{$fish->id}/knowledge/{$note->id}/edit");
            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('EditFishNote')
                    ->where('fish.id', $fish->id)
                    ->where('note.id', $note->id)
            );
        });

        it('can navigate from fish detail to audio list and back', function () {
            $fish = Fish::factory()->create(['name' => 'Integration Test Fish']);
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Test pronunciation',
                'locate' => 'test-audio.mp3'
            ]);

            // Navigate to audio list
            $response = $this->get("/fish/{$fish->id}/audio-list");
            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('FishAudioList')
                    ->where('fish.id', $fish->id)
                    ->has('fish.audios')
            );

            // Navigate to edit audio
            $response = $this->get("/fish/{$fish->id}/audio/{$audio->id}/edit");
            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('EditFishAudio')
                    ->where('fish.id', $fish->id)
                    ->where('audio.id', $audio->id)
            );
        });
    });

    describe('Complete CRUD Operations Flow', function () {
        it('can perform complete knowledge management workflow', function () {
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Original knowledge',
                'note_type' => '生態習性'
            ]);

            // 1. View knowledge list
            $response = $this->get("/fish/{$fish->id}/knowledge-list");
            $response->assertStatus(200);

            // 2. Edit knowledge
            $response = $this->get("/fish/{$fish->id}/knowledge/{$note->id}/edit");
            $response->assertStatus(200);

            // 3. Update knowledge
            $response = $this->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => 'Updated knowledge',
                'note_type' => '營養價值'
            ]);
            $response->assertRedirect("/fish/{$fish->id}/knowledge-list");

            // 4. Verify update in database
            $this->assertDatabaseHas('fish_notes', [
                'id' => $note->id,
                'note' => 'Updated knowledge',
                'note_type' => '營養價值'
            ]);

            // 5. Delete knowledge
            $response = $this->delete("/fish/{$fish->id}/knowledge/{$note->id}");
            $response->assertRedirect("/fish/{$fish->id}/knowledge-list");

            // 6. Verify soft delete
            $this->assertSoftDeleted('fish_notes', [
                'id' => $note->id
            ]);
        });

        it('can perform complete audio management workflow', function () {
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original pronunciation',
                'locate' => 'original.mp3'
            ]);

            // 1. View audio list
            $response = $this->get("/fish/{$fish->id}/audio-list");
            $response->assertStatus(200);

            // 2. Edit audio
            $response = $this->get("/fish/{$fish->id}/audio/{$audio->id}/edit");
            $response->assertStatus(200);

            // 3. Update audio
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Updated pronunciation',
                'audio_filename' => 'updated.mp3'
            ]);
            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            // 4. Verify update in database
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Updated pronunciation',
                'locate' => 'updated.mp3'
            ]);

            // 5. Delete audio
            $response = $this->delete("/fish/{$fish->id}/audio/{$audio->id}");
            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            // 6. Verify soft delete
            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id
            ]);
        });
    });

    describe('Data Consistency and Integrity', function () {
        it('maintains data consistency across knowledge operations', function () {
            $fish = Fish::factory()->create();
            
            // Create multiple notes
            $note1 = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'First knowledge',
                'note_type' => '生態習性'
            ]);
            $note2 = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Second knowledge',
                'note_type' => '生態習性'
            ]);

            // Update one note
            $this->put("/fish/{$fish->id}/knowledge/{$note1->id}", [
                'note' => 'Updated first knowledge',
                'note_type' => '營養價值'
            ]);

            // Verify the other note is unchanged
            $this->assertDatabaseHas('fish_notes', [
                'id' => $note2->id,
                'note' => 'Second knowledge',
                'note_type' => '生態習性'
            ]);

            // Verify the updated note
            $this->assertDatabaseHas('fish_notes', [
                'id' => $note1->id,
                'note' => 'Updated first knowledge',
                'note_type' => '營養價值'
            ]);
        });

        it('maintains data consistency across audio operations', function () {
            $fish = Fish::factory()->create();
            
            // Create multiple audio files
            $audio1 = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'First pronunciation',
                'locate' => 'first.mp3'
            ]);
            $audio2 = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Second pronunciation',
                'locate' => 'second.mp3'
            ]);

            // Update one audio
            $this->put("/fish/{$fish->id}/audio/{$audio1->id}", [
                'name' => 'Updated first pronunciation'
            ]);

            // Verify the other audio is unchanged
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio2->id,
                'name' => 'Second pronunciation',
                'locate' => 'second.mp3'
            ]);

            // Verify the updated audio
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio1->id,
                'name' => 'Updated first pronunciation',
                'locate' => 'first.mp3' // Should remain unchanged
            ]);
        });

        it('handles fish deletion impact on knowledge and audio', function () {
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // Soft delete the fish
            $fish->delete();

            // Knowledge and audio should still exist in database
            $this->assertDatabaseHas('fish_notes', ['id' => $note->id]);
            $this->assertDatabaseHas('fish_audios', ['id' => $audio->id]);

            // But accessing through deleted fish should fail
            $response = $this->get("/fish/{$fish->id}/knowledge-list");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);

            $response = $this->get("/fish/{$fish->id}/audio-list");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('User Experience Flow', function () {
        it('provides consistent user experience across management pages', function () {
            $fish = Fish::factory()->create(['name' => 'UX Test Fish']);
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // Both management pages should show fish information
            $knowledgeResponse = $this->get("/fish/{$fish->id}/knowledge-list");
            $audioResponse = $this->get("/fish/{$fish->id}/audio-list");

            $knowledgeResponse->assertInertia(
                fn ($page) =>
                $page->where('fish.name', 'UX Test Fish')
            );

            $audioResponse->assertInertia(
                fn ($page) =>
                $page->where('fish.name', 'UX Test Fish')
            );

            // Both edit pages should show fish information
            $editKnowledgeResponse = $this->get("/fish/{$fish->id}/knowledge/{$note->id}/edit");
            $editAudioResponse = $this->get("/fish/{$fish->id}/audio/{$audio->id}/edit");

            $editKnowledgeResponse->assertInertia(
                fn ($page) =>
                $page->where('fish.name', 'UX Test Fish')
            );

            $editAudioResponse->assertInertia(
                fn ($page) =>
                $page->where('fish.name', 'UX Test Fish')
            );
        });

        it('handles validation errors consistently across forms', function () {
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // Test knowledge validation
            $response = $this->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => '', // Invalid empty note
                'note_type' => '生態習性'
            ]);
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['note']);

            // Test audio validation
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => '' // Invalid empty name
            ]);
            $response->assertSessionHasErrors(['name']);
        });

        it('provides proper success feedback after operations', function () {
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            // Test knowledge update success
            $response = $this->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => 'Updated knowledge',
                'note_type' => '生態習性'
            ]);
            $response->assertRedirect("/fish/{$fish->id}/knowledge-list");
            $response->assertSessionHas('success');

            // Test audio update success
            $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Updated audio'
            ]);
            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            // Test knowledge delete success
            $response = $this->delete("/fish/{$fish->id}/knowledge/{$note->id}");
            $response->assertRedirect("/fish/{$fish->id}/knowledge-list");
            $response->assertSessionHas('success');

            // Test audio delete success
            $response = $this->delete("/fish/{$fish->id}/audio/{$audio->id}");
            $response->assertRedirect("/fish/{$fish->id}/audio-list");
        });
    });

    describe('Performance and Scalability', function () {
        it('handles multiple knowledge entries efficiently', function () {
            $fish = Fish::factory()->create();
            
            // Create many knowledge entries
            $notes = FishNote::factory()->count(50)->create([
                'fish_id' => $fish->id,
                'note_type' => '生態習性'
            ]);

            $startTime = microtime(true);
            $response = $this->get("/fish/{$fish->id}/knowledge-list");
            $endTime = microtime(true);

            $response->assertStatus(200);
            
            // Should complete within reasonable time (less than 1 second)
            expect($endTime - $startTime)->toBeLessThan(1.0);

            // Should properly group all notes
            $response->assertInertia(function ($page) {
                $groupedNotes = $page->toArray()['props']['groupedNotes'];
                $habitatGroup = collect($groupedNotes)->firstWhere('name', '生態習性');
                expect(count($habitatGroup['notes']))->toBe(50);
                return $page;
            });
        });

        it('handles multiple audio entries efficiently', function () {
            $fish = Fish::factory()->create();
            
            // Create many audio entries
            FishAudio::factory()->count(20)->create([
                'fish_id' => $fish->id
            ]);

            $startTime = microtime(true);
            $response = $this->get("/fish/{$fish->id}/audio-list");
            $endTime = microtime(true);

            $response->assertStatus(200);
            
            // Should complete within reasonable time
            expect($endTime - $startTime)->toBeLessThan(1.0);

            // Should load all audio entries
            $response->assertInertia(
                fn ($page) =>
                $page->has('fish.audios', 20)
            );
        });
    });

    describe('Cross-Feature Integration', function () {
        it('maintains proper relationships between fish, knowledge, and audio', function () {
            $fish1 = Fish::factory()->create(['name' => 'Fish One']);
            $fish2 = Fish::factory()->create(['name' => 'Fish Two']);

            $note1 = FishNote::factory()->create(['fish_id' => $fish1->id, 'note' => 'Fish 1 knowledge']);
            $note2 = FishNote::factory()->create(['fish_id' => $fish2->id, 'note' => 'Fish 2 knowledge']);

            $audio1 = FishAudio::factory()->create(['fish_id' => $fish1->id, 'name' => 'Fish 1 audio']);
            $audio2 = FishAudio::factory()->create(['fish_id' => $fish2->id, 'name' => 'Fish 2 audio']);

            // Fish 1 should only see its own knowledge and audio
            $response = $this->get("/fish/{$fish1->id}/knowledge-list");
            $response->assertInertia(function ($page) {
                $groupedNotes = $page->toArray()['props']['groupedNotes'];
                $allNotes = collect($groupedNotes)->flatMap(fn ($group) => $group['notes']);
                expect($allNotes->pluck('note')->toArray())->toBe(['Fish 1 knowledge']);
                return $page;
            });

            $response = $this->get("/fish/{$fish1->id}/audio-list");
            $response->assertInertia(
                fn ($page) =>
                $page->where('fish.audios.0.name', 'Fish 1 audio')
                    ->has('fish.audios', 1)
            );

            // Fish 2 should only see its own knowledge and audio
            $response = $this->get("/fish/{$fish2->id}/knowledge-list");
            $response->assertInertia(function ($page) {
                $groupedNotes = $page->toArray()['props']['groupedNotes'];
                $allNotes = collect($groupedNotes)->flatMap(fn ($group) => $group['notes']);
                expect($allNotes->pluck('note')->toArray())->toBe(['Fish 2 knowledge']);
                return $page;
            });

            $response = $this->get("/fish/{$fish2->id}/audio-list");
            $response->assertInertia(
                fn ($page) =>
                $page->where('fish.audios.0.name', 'Fish 2 audio')
                    ->has('fish.audios', 1)
            );
        });
    });
});
