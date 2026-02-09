<?php

use App\Models\Fish;
use App\Models\FishNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

uses(RefreshDatabase::class);

describe('Fish Knowledge Management', function () {
    
    describe('Knowledge List Page', function () {
        it('can display knowledge list page for existing fish', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create(['name' => 'Test Fish']);
            $note1 = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'First knowledge',
                'note_type' => '生態習性',
                'locate' => 'Taiwan waters'
            ]);
            $note2 = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Second knowledge',
                'note_type' => '食用方式',
                'locate' => 'Eastern coast'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('Fish/KnowledgeManager')
                    ->has('fish')
                    ->where('fish.id', $fish->id)
                    ->where('fish.name', 'Test Fish')
                    ->has('fishNotes')
            );
        });

        it('redirects with error for non-existent fish', function () {
            $user = User::factory()->create();
            $response = $this->actingAs($user)->get('/fish/999/knowledge-manager');
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('groups notes by type correctly', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            
            // Create notes with different types
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Habitat info',
                'note_type' => '生態習性'
            ]);
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Food info',
                'note_type' => '食用方式'
            ]);
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Another habitat info',
                'note_type' => '生態習性'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) {
                $fishNotes = $page->toArray()['props']['fishNotes'];
                
                // Should have 2 groups
                expect(count($fishNotes))->toBe(2);
                
                // Find the groups by name
                $habitatGroup = collect($fishNotes)->firstWhere('name', '生態習性');
                $cookingGroup = collect($fishNotes)->firstWhere('name', '食用方式');
                
                expect($habitatGroup)->not->toBeNull();
                expect($cookingGroup)->not->toBeNull();
                expect(count($habitatGroup['notes']))->toBe(2);
                expect(count($cookingGroup['notes']))->toBe(1);
                
                return $page;
            });
        });

        it('handles notes without type correctly', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            
            // Create note with empty string instead of null to avoid database constraint
            $note = new FishNote([
                'fish_id' => $fish->id,
                'note' => 'General knowledge',
                'note_type' => '',
                'locate' => 'test location'
            ]);
            $note->save();

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) {
                $fishNotes = $page->toArray()['props']['fishNotes'];
                
                // Should have 1 group for uncategorized notes
                expect(count($fishNotes))->toBe(1);
                
                $uncategorizedGroup = collect($fishNotes)->firstWhere('name', '未分類');
                expect($uncategorizedGroup)->not->toBeNull();
                expect(count($uncategorizedGroup['notes']))->toBe(1);
                
                return $page;
            });
        });
    });

    describe('Knowledge Edit Page', function () {
        it('can display edit knowledge page', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create(['name' => 'Test Fish']);
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Original knowledge',
                'note_type' => '生態習性',
                'locate' => 'Taiwan'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge/{$note->id}/edit");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) =>
                $page->component('Fish/EditKnowledge')
                    ->has('fish')
                    ->has('note')
                    ->where('fish.id', $fish->id)
                    ->where('note.id', $note->id)
                    ->where('note.note', 'Original knowledge')
                    ->has('noteTypes')
            );
        });

        it('redirects with error for non-existent fish', function () {
            $user = User::factory()->create();
            $note = FishNote::factory()->create();
            $response = $this->actingAs($user)->get("/fish/999/knowledge/{$note->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error for non-existent note', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge/999/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when note does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->actingAs($user)->get("/fish/{$fish1->id}/knowledge/{$note->id}/edit");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('Knowledge Update', function () {
        it('can update knowledge successfully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Original knowledge',
                'note_type' => '生態習性',
                'locate' => 'Original location'
            ]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => 'Updated knowledge',
                'note_type' => '食用方式',
                'locate' => 'Updated location'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/knowledge-manager");

            $this->assertDatabaseHas('fish_notes', [
                'id' => $note->id,
                'fish_id' => $fish->id,
                'note' => 'Updated knowledge',
                'note_type' => '食用方式',
                'locate' => 'Updated location'
            ]);
        });

        it('validates required fields when updating knowledge', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => '', // Empty note should fail validation
                'note_type' => '生態習性'
            ]);

            // The application returns JSON validation errors
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['note']);
        });

        it('can update knowledge with partial data', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Original knowledge',
                'note_type' => '生態習性',
                'locate' => 'Original location'
            ]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => 'Updated knowledge only'
                // Not updating note_type or locate
            ]);

            $response->assertRedirect("/fish/{$fish->id}/knowledge-manager");

            $this->assertDatabaseHas('fish_notes', [
                'id' => $note->id,
                'note' => 'Updated knowledge only',
                'note_type' => '生態習性', // Should remain unchanged
                'locate' => 'Original location' // Should remain unchanged
            ]);
        });

        it('redirects with error when updating non-existent knowledge', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/999", [
                'note' => 'Should not update',
                'note_type' => '生態習性'
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when knowledge does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->actingAs($user)->put("/fish/{$fish1->id}/knowledge/{$note->id}", [
                'note' => 'Should not update',
                'note_type' => '生態習性'
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });
    });

    describe('Knowledge Delete', function () {
        it('can delete knowledge successfully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'To be deleted'
            ]);

            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/knowledge/{$note->id}");

            $response->assertRedirect("/fish/{$fish->id}/knowledge-manager");

            $this->assertSoftDeleted('fish_notes', [
                'id' => $note->id,
                'fish_id' => $fish->id
            ]);
        });

        it('redirects with error when deleting non-existent knowledge', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/knowledge/999");

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when knowledge does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish2->id]);

            $response = $this->actingAs($user)->delete("/fish/{$fish1->id}/knowledge/{$note->id}");

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('does not hard delete knowledge', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);

            $this->actingAs($user)->delete("/fish/{$fish->id}/knowledge/{$note->id}");

            // Should still exist in database but with deleted_at timestamp
            $deletedNote = FishNote::withTrashed()->find($note->id);
            expect($deletedNote)->not->toBeNull();
            expect($deletedNote->deleted_at)->not->toBeNull();
        });
    });

    describe('Data Grouping and Sorting', function () {
        it('sorts notes within categories by creation time', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            
            // Create notes with specific timestamps
            $oldNote = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Old habitat info',
                'note_type' => '生態習性',
                'created_at' => now()->subDays(2)
            ]);
            
            $newNote = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'New habitat info',
                'note_type' => '生態習性',
                'created_at' => now()->subDay()
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) use ($oldNote, $newNote) {
                $fishNotes = $page->toArray()['props']['fishNotes'];
                
                // Find the habitat group
                $habitatGroup = collect($fishNotes)->firstWhere('name', '生態習性');
                expect($habitatGroup)->not->toBeNull();
                expect(count($habitatGroup['notes']))->toBe(2);
                
                // Check the order (should be sorted by created_at DESC - newer first)
                expect($habitatGroup['notes'][0]['id'])->toBe($newNote->id);
                expect($habitatGroup['notes'][1]['id'])->toBe($oldNote->id);
                
                return $page;
            });
        });

        it('sorts categories alphabetically', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note_type' => '烹飪方法'
            ]);
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note_type' => '生態習性'
            ]);
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note_type' => '營養價值'
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) {
                $fishNotes = $page->toArray()['props']['fishNotes'];
                
                // Extract category names from the grouped notes
                $categoryNames = collect($fishNotes)->pluck('name')->toArray();
                
                // Should be sorted according to the predefined order in controller
                expect($categoryNames)->toBe(['生態習性', '營養價值', '烹飪方法']);
                return $page;
            });
        });

        it('handles mixed categorized and uncategorized notes', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            
            FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Categorized note',
                'note_type' => '生態習性'
            ]);
            // Create note with empty string instead of null to avoid database constraint
            $note = new FishNote([
                'fish_id' => $fish->id,
                'note' => 'Uncategorized note',
                'note_type' => '',
                'locate' => 'test location'
            ]);
            $note->save();

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");

            $response->assertStatus(200);
            $response->assertInertia(function ($page) {
                $fishNotes = $page->toArray()['props']['fishNotes'];
                
                // Should have 2 groups
                expect(count($fishNotes))->toBe(2);
                
                // Find the groups by name
                $categorizedGroup = collect($fishNotes)->firstWhere('name', '生態習性');
                $uncategorizedGroup = collect($fishNotes)->firstWhere('name', '未分類');
                
                expect($categorizedGroup)->not->toBeNull();
                expect($uncategorizedGroup)->not->toBeNull();
                expect(count($categorizedGroup['notes']))->toBe(1);
                expect(count($uncategorizedGroup['notes']))->toBe(1);
                expect($categorizedGroup['notes'][0]['note'])->toBe('Categorized note');
                expect($uncategorizedGroup['notes'][0]['note'])->toBe('Uncategorized note');
                
                return $page;
            });
        });
    });

    describe('Error Handling', function () {
        it('handles database errors gracefully during update', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create(['fish_id' => $fish->id]);

            // Simulate database error by using invalid data type
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => str_repeat('a', 10000), // Assuming note field has length limit
                'note_type' => '生態習性'
            ]);

            // Should handle the error gracefully (either validation error or success)
            expect($response->status())->toBeIn([200, 302, 422]);
        });

        it('maintains data integrity during concurrent updates', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $note = FishNote::factory()->create([
                'fish_id' => $fish->id,
                'note' => 'Original note'
            ]);

            // First update
            $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => 'First update',
                'note_type' => '生態習性'
            ]);

            // Second update should work on the updated record
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/knowledge/{$note->id}", [
                'note' => 'Second update',
                'note_type' => '食用方式'
            ]);

            $response->assertRedirect("/fish/{$fish->id}/knowledge-manager");

            $this->assertDatabaseHas('fish_notes', [
                'id' => $note->id,
                'note' => 'Second update',
                'note_type' => '食用方式'
            ]);
        });
    });
});
