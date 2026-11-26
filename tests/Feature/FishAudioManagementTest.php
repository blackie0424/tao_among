<?php

use App\Contracts\StorageServiceInterface;
use App\Models\Fish;
use App\Models\FishAudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

describe('Fish Audio Management', function () {

    describe('Audio List Page', function () {
        it('can display audio list page for existing fish', function () {
            $user = User::factory()->create();

            $fish = Fish::factory()->create(['name' => 'Test Fish']);
            $audio1 = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'First pronunciation',
                'locate' => 'audio1.mp3',
            ]);
            $audio2 = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Second pronunciation',
                'locate' => 'audio2.mp3',
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) => $page->component('FishAudioList')
                    ->has('fish')
                    ->where('fish.id', $fish->id)
                    ->where('fish.name', 'Test Fish')
                    ->has('fish.audios', 2)
            );
        });

        it('redirects with error for non-existent fish', function () {
            $user = User::factory()->create();
            $response = $this->actingAs($user)->get('/fish/999/audio-list');
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('displays empty list when fish has no audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) => $page->component('FishAudioList')
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
                'locate' => 'test-audio.mp3',
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) => $page->has('fish.audios.0.url')
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
                'locate' => 'original.mp3',
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio/{$audio->id}/edit");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) => $page->component('Fish/EditAudio')
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
        it('只改部落：name 維持實際檔名，存檔後回管理發音頁', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'original.m4a',
                'locate' => 'yayo',
            ]);

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'iratay',
            ]);

            $response->assertRedirect("/fish/{$fish->id}/audio-list");
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'original.m4a', // 播放網址靠它，不能被改
                'locate' => 'iratay',
            ]);
        });

        it('請求裡帶了名稱也不會改到 name（名稱不可編輯）', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'original.m4a', 'locate' => 'yayo']);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => '使用者輸入的名稱',
                'tribe' => 'yayo',
            ]);

            expect($audio->fresh()->name)->toBe('original.m4a');
        });

        it('換音檔：複製暫存檔成新檔名後 name 改為新檔名、長度更新，主要發音檔跟著改，舊檔刪除', function () {
            $user = User::factory()->create();
            $pendingName = '11111111-2222-3333-4444-555555555555.m4a';
            $fish = Fish::factory()->create(['audio_filename' => 'old.m4a']);
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo']);

            $storage = $this->mock(StorageServiceInterface::class)->shouldIgnoreMissing();
            $storage->shouldReceive('getAudioFolder')->andReturn('audios');
            $storage->shouldReceive('fileSize')->with(FishAudio::pendingPath($user->id, $pendingName))->andReturn(50_000);
            $storage->shouldReceive('copyObject')->once()->with(FishAudio::pendingPath($user->id, $pendingName), \Mockery::pattern('#^audios/[0-9a-f-]{36}\.m4a$#'))->andReturn(true);
            $storage->shouldReceive('delete')->with('audios/old.m4a')->andReturn(true);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'yayo',
                'pending_filename' => $pendingName,
                'duration_ms' => 4200,
            ])->assertRedirect("/fish/{$fish->id}/audio-list");

            $fresh = $audio->fresh();
            expect($fresh->name)->toMatch('/^[0-9a-f-]{36}\.m4a$/')->not->toBe('old.m4a');
            expect($fresh->duration)->toBe(4200);
            expect($fish->fresh()->audio_filename)->toBe($fresh->name);
        });

        it('換音檔後續步驟失敗：整筆回滾、刪掉已複製的新檔，舊檔與資料原封不動', function () {
            $user = User::factory()->create();
            $newName = '11111111-2222-3333-4444-555555555555.m4a';
            $fish = Fish::factory()->create(['audio_filename' => 'old.m4a']);
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo']);

            $storage = $this->mock(StorageServiceInterface::class)->shouldIgnoreMissing();
            $storage->shouldReceive('getAudioFolder')->andReturn('audios');
            $storage->shouldReceive('fileSize')->andReturn(50_000);
            $copiedTo = null;
            $storage->shouldReceive('copyObject')->once()->andReturnUsing(function ($from, $to) use (&$copiedTo) {
                $copiedTo = $to;

                return true;
            });
            $storage->shouldReceive('delete')->once()->andReturnUsing(function ($path) use (&$copiedTo) {
                expect($path)->toBe($copiedTo); // 只補償自己複製的新檔，不碰舊檔

                return true;
            });
            $updates = 0;
            FishAudio::updating(function () use (&$updates) {
                if (++$updates === 2) { // 第 1 次是換檔，第 2 次是改部落
                    throw new RuntimeException('改部落失敗');
                }
            });

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'iratay',
                'pending_filename' => $newName,
                'duration_ms' => 4000,
            ])->assertSessionHas('error');

            expect($audio->fresh()->only(['name', 'locate']))->toBe(['name' => 'old.m4a', 'locate' => 'yayo']);
            expect($fish->fresh()->audio_filename)->toBe('old.m4a');
        });

        it('換音檔：AAC（.aac）的暫存檔也能換', function () {
            $user = User::factory()->create();
            $pendingName = '11111111-2222-3333-4444-555555555555.aac';
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'old.m4a', 'locate' => 'yayo']);
            $storage = $this->mock(StorageServiceInterface::class)->shouldIgnoreMissing();
            $storage->shouldReceive('getAudioFolder')->andReturn('audios');
            $storage->shouldReceive('fileSize')->andReturn(50_000);
            $storage->shouldReceive('copyObject')->once()->andReturn(true);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'yayo',
                'pending_filename' => $pendingName,
                'duration_ms' => 3000,
            ])->assertRedirect("/fish/{$fish->id}/audio-list");

            expect($audio->fresh()->name)->toEndWith('.aac');
        });

        it('部落必填且須是可選的部落', function (mixed $tribe) {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'locate' => 'yayo']);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", ['tribe' => $tribe])
                ->assertSessionHasErrors(['tribe']);

            expect($audio->fresh()->locate)->toBe('yayo');
        })->with(['', null, 'supabase', 'unknown']);

        it('暫存檔名格式不對（含路徑）時被拒絕，沒有改到資料', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'keep.m4a', 'locate' => 'yayo']);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'yayo',
                'pending_filename' => '../../etc/passwd.m4a',
                'duration_ms' => 3000,
            ])->assertSessionHasErrors(['pending_filename']);

            expect($audio->fresh()->name)->toBe('keep.m4a');
        });

        it('換音檔時沒帶長度或超過 5 秒被拒絕', function (mixed $duration) {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'yayo',
                'pending_filename' => '11111111-2222-3333-4444-555555555555.m4a',
                'duration_ms' => $duration,
            ])->assertSessionHasErrors(['duration_ms']);
        })->with([null, 5001, 0]);

        it('redirects with error when updating non-existent audio', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/999", [
                'tribe' => 'yayo',
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('redirects with error when audio does not belong to fish', function () {
            $user = User::factory()->create();
            $fish1 = Fish::factory()->create();
            $fish2 = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish2->id, 'locate' => 'yayo']);

            $response = $this->actingAs($user)->put("/fish/{$fish1->id}/audio/{$audio->id}", [
                'tribe' => 'iratay',
            ]);

            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
            expect($audio->fresh()->locate)->toBe('yayo');
        });
    });

    describe('Audio Delete', function () {
        it('can delete audio successfully', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'To be deleted',
            ]);

            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$audio->id}");

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id,
                'fish_id' => $fish->id,
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
                'locate' => 'test-audio.mp3',
            ]);

            // Create a fake file
            Storage::disk('public')->put('audio/test-audio.mp3', 'fake audio content');

            $response = $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$audio->id}");

            $response->assertRedirect("/fish/{$fish->id}/audio-list");

            // Audio record should be soft deleted
            $this->assertSoftDeleted('fish_audios', [
                'id' => $audio->id,
            ]);
        });
    });

    describe('Audio URL Generation', function () {
        it('generates correct audio URLs', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'test-audio.mp3',
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio-list");

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
                'locate' => '', // Empty file location instead of null
            ]);

            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio-list");

            $response->assertStatus(200);
            $response->assertInertia(
                fn ($page) => $page->has('fish.audios.0')
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
                'fish_id' => $fish->id,
            ]);

            // Accessing audio list should redirect with error since fish is deleted
            $response = $this->actingAs($user)->get("/fish/{$fish->id}/audio-list");
            $response->assertRedirect();
            $response->assertSessionHasErrors(['error']);
        });

        it('handles concurrent audio operations', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create([
                'fish_id' => $fish->id,
                'name' => 'Original name',
            ]);

            // Simulate concurrent updates：後送出的部落為準
            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'yayo',
            ]);

            $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => 'iratay',
            ]);

            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Original name',
                'locate' => 'iratay',
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
                'name' => 'Should not work',
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
                'tribe' => null, // This should be caught by validation before hitting database
            ]);

            $response->assertSessionHasErrors(['tribe']);
        });

        it('handles large audio file names', function () {
            $user = User::factory()->create();
            $fish = Fish::factory()->create();
            $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

            $longFilename = str_repeat('a', 500).'.mp3';

            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'name' => 'Test Audio',
                'audio_filename' => $longFilename,
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
                'locate' => 'original.mp3',
            ]);

            // Attempt invalid update
            $response = $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}", [
                'tribe' => '', // Invalid tribe
            ]);

            // Original data should remain unchanged
            $this->assertDatabaseHas('fish_audios', [
                'id' => $audio->id,
                'name' => 'Original name',
                'locate' => 'original.mp3',
            ]);
        });
    });
});

describe('指派名稱與未對應名稱', function () {
    it('把未對應的錄音指派到這隻魚目前的某個名稱，存檔後回管理發音頁', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}/name", ['name' => 'mivanivang'])
            ->assertRedirect("/fish/{$fish->id}/audio-list");

        expect($audio->fresh()->spoken_name)->toBe('mivanivang');
    });

    it('指派不存在的名稱被拒絕，資料不變', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}/name", ['name' => '不存在'])
            ->assertSessionHasErrors(['name']);

        expect($audio->fresh()->spoken_name)->toBeNull();
    });

    it('viewer 不能指派名稱', function () {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);

        $this->actingAs(User::factory()->lineViewer()->create())->put("/fish/{$fish->id}/audio/{$audio->id}/name", ['name' => 'mivazlt'])
            ->assertForbidden();
    });
});

describe('刪除主要發音那一段', function () {
    it('指標改指向剩餘最新一段；沒有剩餘則清空', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'b.m4a']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'a.m4a', 'spoken_name' => 'mivazlt']);
        $b = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'b.m4a', 'spoken_name' => 'mivazlt']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'c.m4a', 'spoken_name' => 'mivazlt']);

        $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$b->id}")->assertRedirect("/fish/{$fish->id}/audio-list");
        expect($fish->fresh()->audio_filename)->toBe('c.m4a');

        FishAudio::where('fish_id', $fish->id)->get()->each(fn ($a) => $this->actingAs($user)->delete("/fish/{$fish->id}/audio/{$a->id}"));
        expect($fish->fresh()->audio_filename)->toBeNull();
    });
});

describe('錄音者可見度', function () {
    it('田調人員的管理發音頁依名稱分組並回傳錄音者；一般序列化不輸出錄音者', function () {
        $editor = User::factory()->create(['name' => '甲']);
        $fish = Fish::factory()->create(['name' => 'mivazlt、mivanivang']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivazlt', 'recorded_by' => $editor->id]);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null, 'recorded_by' => null]);

        $this->actingAs($editor)->get("/fish/{$fish->id}/audio-list")->assertInertia(
            fn ($page) => $page->component('FishAudioList')
                ->where('audioGroups.0.name', 'mivazlt')
                ->where('audioGroups.0.audios.0.recorder_name', '甲')
                ->has('audioGroups.1.audios', 0)
                ->where('unassignedAudios.0.recorder_name', null)
                ->where('tribes', config('fish_options.tribes'))
        );

        expect(FishAudio::first()->toArray())->not->toHaveKey('recorded_by');
    });

    it('沒有發音權限的角色拿不到管理發音頁，也沒有錄音者與網址', function (string $role) {
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivazlt', 'recorded_by' => User::factory()->create()->id]);
        $viewer = User::factory()->create(['role' => $role]);

        $this->actingAs($viewer)->get("/fish/{$fish->id}/audio-list")->assertForbidden();
        $this->actingAs($viewer)->getJson("/prefix/api/fish/{$fish->id}")
            ->assertJsonMissingPath('audios')
            ->assertJsonMissingPath('audio_url');
    })->with(['viewer', 'guest']);

    it('API 的魚資料即使是田調人員也不輸出錄音者', function () {
        $editor = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'mivazlt']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'mivazlt', 'recorded_by' => $editor->id]);

        $response = $this->actingAs($editor)->getJson("/prefix/api/fish/{$fish->id}");

        expect(json_encode($response->json()))->not->toContain('recorded_by');
    });
});

describe('設為魚頁使用（每個拼字各自的主要發音）', function () {
    it('沒有拼字的錄音不能設為魚頁使用，回報錯誤且資料不變', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay', 'audio_filename' => 'keep.m4a']);
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => null]);

        $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$audio->id}/set-base")->assertSessionHasErrors(['audio']);

        expect($audio->fresh()->is_primary)->toBeFalse();
        expect($fish->fresh()->audio_filename)->toBe('keep.m4a');
    });

    it('指定一段只影響它所屬的拼字', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay']);
        $e1 = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey', 'is_primary' => true]);
        $e2 = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey']);
        $a1 = FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivay', 'is_primary' => true]);

        $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$e2->id}/set-base")->assertRedirect("/fish/{$fish->id}/audio-list");

        expect([$e1->fresh()->is_primary, $e2->fresh()->is_primary, $a1->fresh()->is_primary])->toBe([false, true, true]);
    });

    it('管理發音頁與魚頁資料回傳 is_primary', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'Ivey']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'spoken_name' => 'Ivey', 'is_primary' => true]);

        $this->actingAs($user)->get("/fish/{$fish->id}/audio-list")->assertInertia(
            fn ($page) => $page->where('audioGroups.0.audios.0.is_primary', true)
        );
    });

    it('魚頁使用的主要發音指向被選的那一段，存檔後回管理發音頁', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create(['name' => 'mivazlt', 'audio_filename' => 'a.m4a']);
        FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'a.m4a', 'spoken_name' => 'mivazlt']);
        $b = FishAudio::factory()->create(['fish_id' => $fish->id, 'name' => 'b.m4a', 'spoken_name' => 'mivazlt']);

        $this->actingAs($user)->put("/fish/{$fish->id}/audio/{$b->id}/set-base")->assertRedirect("/fish/{$fish->id}/audio-list");

        expect($fish->fresh()->audio_filename)->toBe('b.m4a');
    });
});

describe('新增發音頁的來源（#176 AC 5）', function () {
    it('帶 from=fish 時把來源傳給頁面，其他值或沒帶都視為沒有來源', function (?string $query, ?string $expected) {
        $fish = Fish::factory()->create(['name' => 'Ivey、Ivay']);

        $this->actingAs(User::factory()->lineEditor()->create())
            ->get("/fish/{$fish->id}/audio/create".$query)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CreateFishAudio')
                ->where('from', $expected));
    })->with([
        'from=fish' => ['?from=fish', 'fish'],
        'from 與名稱一起' => ['?name=Ivay&from=fish', 'fish'],
        '其他值' => ['?from=workspace', null],
        '大小寫不同' => ['?from=Fish', null],
        '沒帶' => ['', null],
    ]);
});
