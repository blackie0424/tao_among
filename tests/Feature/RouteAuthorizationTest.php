<?php

/**
 * 路由權限測試
 * 確保公開路由和需要登入的路由設定正確，避免修改時不小心改動
 */

use App\Models\Fish;
use App\Models\User;
use App\Models\FishNote;
use App\Models\FishAudio;
use App\Models\CaptureRecord;
use App\Models\TribalClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// =====================================================
// 公開路由測試（不需登入即可存取）
// =====================================================

describe('公開路由（不需登入）', function () {
    
    it('首頁可公開存取', function () {
        $response = $this->get('/');
        $response->assertStatus(200);
    });

    it('魚類列表可公開存取', function () {
        Http::fake(['*' => Http::response('', 404)]);
        
        $response = $this->get('/fishs');
        $response->assertStatus(200);
    });

    it('搜尋頁面可公開存取', function () {
        Http::fake(['*' => Http::response('', 404)]);
        
        $response = $this->get('/search');
        $response->assertStatus(200);
    });

    it('魚類詳細頁面可公開存取', function () {
        $fish = Fish::factory()->create();
        Http::fake(['*' => Http::response('', 404)]);
        
        $response = $this->get("/fish/{$fish->id}");
        $response->assertStatus(200);
    });

    it('登入頁面可公開存取', function () {
        $response = $this->get('/login');
        $response->assertStatus(200);
    });
});

// =====================================================
// 需要登入的路由測試（未登入應導向登入頁面）
// =====================================================

describe('需要登入的路由（未登入應導向登入頁面）', function () {

    // -------------------------------------------------
    // 魚類基本管理 - GET 請求
    // -------------------------------------------------
    
    it('新增魚類頁面需要登入', function () {
        $response = $this->get('/fish/create');
        $response->assertRedirect('/login');
    });

    it('編輯魚名頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/edit");
        $response->assertRedirect('/login');
    });

    it('合併魚類頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/merge");
        $response->assertRedirect('/login');
    });

    // -------------------------------------------------
    // 魚類基本管理 - 寫入請求（POST/PUT/DELETE）
    // -------------------------------------------------

    it('儲存魚類需要登入', function () {
        $response = $this->post('/fish', ['name' => 'Test', 'image' => 'test.jpg']);
        // 驗證不是 200 成功，而是被拒絕存取（302 redirect 或 401/403）
        expect($response->status())->not->toBe(200);
        expect($response->status())->not->toBe(201);
    });

    it('更新魚名需要登入', function () {
        $fish = Fish::factory()->create();
        $originalName = $fish->name;
        $response = $this->put("/fish/{$fish->id}/name", ['name' => 'New Name']);
        // 確認資料未被修改（驗證 auth 保護有效）
        $fish->refresh();
        expect($fish->name)->toBe($originalName);
    });

    it('刪除魚類需要登入', function () {
        $fish = Fish::factory()->create();
        $fishId = $fish->id;
        $response = $this->delete("/fish/{$fish->id}");
        // 確認資料未被刪除（驗證 auth 保護有效）
        expect(Fish::find($fishId))->not->toBeNull();
    });

    it('設定主圖需要登入', function () {
        $fish = Fish::factory()->create();
        $originalDisplayImageId = $fish->display_capture_record_id;
        $response = $this->put("/fish/{$fish->id}/display-image", ['capture_record_id' => 999]);
        // 確認資料未被修改
        $fish->refresh();
        expect($fish->display_capture_record_id)->toBe($originalDisplayImageId);
    });

    // -------------------------------------------------
    // 聚合管理頁面
    // -------------------------------------------------

    it('照片管理頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/media-manager");
        $response->assertRedirect('/login');
    });

    it('知識管理頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/knowledge-manager");
        $response->assertRedirect('/login');
    });

    // -------------------------------------------------
    // 捕獲紀錄管理 - GET 請求
    // -------------------------------------------------

    it('捕獲紀錄列表頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/capture-records");
        $response->assertRedirect('/login');
    });

    it('新增捕獲紀錄頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/capture-records/create");
        $response->assertRedirect('/login');
    });

    it('編輯捕獲紀錄頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish->id]);
        $response = $this->get("/fish/{$fish->id}/capture-records/{$record->id}/edit");
        $response->assertRedirect('/login');
    });

    // -------------------------------------------------
    // 捕獲紀錄管理 - 寫入請求
    // -------------------------------------------------

    it('儲存捕獲紀錄需要登入', function () {
        $fish = Fish::factory()->create();
        $countBefore = CaptureRecord::count();
        $response = $this->post("/fish/{$fish->id}/capture-records", [
            'image' => 'test.jpg',
            'location' => 'Test Location'
        ]);
        // 確認資料未被新增
        expect(CaptureRecord::count())->toBe($countBefore);
    });

    it('更新捕獲紀錄需要登入', function () {
        $fish = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish->id]);
        $originalLocation = $record->location;
        $response = $this->put("/fish/{$fish->id}/capture-records/{$record->id}", [
            'location' => 'New Location'
        ]);
        $record->refresh();
        expect($record->location)->toBe($originalLocation);
    });

    it('刪除捕獲紀錄需要登入', function () {
        $fish = Fish::factory()->create();
        $record = CaptureRecord::factory()->create(['fish_id' => $fish->id]);
        $recordId = $record->id;
        $response = $this->delete("/fish/{$fish->id}/capture-records/{$record->id}");
        expect(CaptureRecord::find($recordId))->not->toBeNull();
    });

    // -------------------------------------------------
    // 地方知識（部落分類）管理 - GET 請求
    // -------------------------------------------------

    it('部落分類列表頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/tribal-classifications");
        $response->assertRedirect('/login');
    });

    it('新增部落分類頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/tribal-classifications/create");
        $response->assertRedirect('/login');
    });

    it('編輯部落分類頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create(['fish_id' => $fish->id]);
        $response = $this->get("/fish/{$fish->id}/tribal-classifications/{$classification->id}/edit");
        $response->assertRedirect('/login');
    });

    // -------------------------------------------------
    // 地方知識（部落分類）管理 - 寫入請求
    // -------------------------------------------------

    it('儲存部落分類需要登入', function () {
        $fish = Fish::factory()->create();
        $countBefore = TribalClassification::count();
        $response = $this->post("/fish/{$fish->id}/tribal-classifications", [
            'tribe' => 'Test Tribe',
            'food_category' => 'Test Category'
        ]);
        expect(TribalClassification::count())->toBe($countBefore);
    });

    it('更新部落分類需要登入', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create(['fish_id' => $fish->id]);
        $originalTribe = $classification->tribe;
        $response = $this->put("/fish/{$fish->id}/tribal-classifications/{$classification->id}", [
            'tribe' => 'New Tribe'
        ]);
        $classification->refresh();
        expect($classification->tribe)->toBe($originalTribe);
    });

    it('刪除部落分類需要登入', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create(['fish_id' => $fish->id]);
        $classificationId = $classification->id;
        $response = $this->delete("/fish/{$fish->id}/tribal-classifications/{$classification->id}");
        expect(TribalClassification::find($classificationId))->not->toBeNull();
    });

    // -------------------------------------------------
    // 進階知識管理 - GET 請求
    // -------------------------------------------------

    it('知識中心頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/knowledge");
        $response->assertRedirect('/login');
    });

    it('新增知識頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/knowledge/create");
        $response->assertRedirect('/login');
    });

    it('知識列表頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/knowledge-list");
        $response->assertRedirect('/login');
    });

    it('編輯知識頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $note = FishNote::factory()->create(['fish_id' => $fish->id]);
        $response = $this->get("/fish/{$fish->id}/knowledge/{$note->id}/edit");
        $response->assertRedirect('/login');
    });

    // -------------------------------------------------
    // 進階知識管理 - 寫入請求
    // -------------------------------------------------

    it('儲存知識需要登入', function () {
        $fish = Fish::factory()->create();
        $countBefore = FishNote::count();
        $response = $this->post("/fish/{$fish->id}/knowledge", [
            'note' => 'Test Note',
            'type' => 'Test Type'
        ]);
        expect(FishNote::count())->toBe($countBefore);
    });

    it('更新知識需要登入', function () {
        $fish = Fish::factory()->create();
        $note = FishNote::factory()->create(['fish_id' => $fish->id]);
        $originalNote = $note->note;
        $response = $this->put("/fish/{$fish->id}/knowledge/{$note->id}", [
            'note' => 'New Note'
        ]);
        $note->refresh();
        expect($note->note)->toBe($originalNote);
    });

    it('刪除知識需要登入', function () {
        $fish = Fish::factory()->create();
        $note = FishNote::factory()->create(['fish_id' => $fish->id]);
        $noteId = $note->id;
        $response = $this->delete("/fish/{$fish->id}/knowledge/{$note->id}");
        expect(FishNote::find($noteId))->not->toBeNull();
    });

    // -------------------------------------------------
    // 發音管理 - GET 請求
    // -------------------------------------------------

    it('新增發音頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/audio/create");
        $response->assertRedirect('/login');
    });

    it('發音列表頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $response = $this->get("/fish/{$fish->id}/audio-list");
        $response->assertRedirect('/login');
    });

    it('編輯發音頁面需要登入', function () {
        $fish = Fish::factory()->create();
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);
        $response = $this->get("/fish/{$fish->id}/audio/{$audio->id}/edit");
        $response->assertRedirect('/login');
    });

    // -------------------------------------------------
    // 發音管理 - 寫入請求
    // -------------------------------------------------

    it('更新發音需要登入', function () {
        $fish = Fish::factory()->create();
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);
        $originalFilename = $audio->filename;
        $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}", [
            'filename' => 'new_audio.mp3'
        ]);
        $audio->refresh();
        expect($audio->filename)->toBe($originalFilename);
    });

    it('設定主發音需要登入', function () {
        $fish = Fish::factory()->create();
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);
        $originalFilename = $audio->filename;
        $response = $this->put("/fish/{$fish->id}/audio/{$audio->id}/set-base", []);
        $audio->refresh();
        expect($audio->filename)->toBe($originalFilename);
    });

    it('刪除發音需要登入', function () {
        $fish = Fish::factory()->create();
        $audio = FishAudio::factory()->create(['fish_id' => $fish->id]);
        $audioId = $audio->id;
        $response = $this->delete("/fish/{$fish->id}/audio/{$audio->id}");
        expect(FishAudio::find($audioId))->not->toBeNull();
    });
});

// =====================================================
// 登入後可存取的路由測試
// =====================================================

describe('登入後可存取管理路由', function () {

    it('登入後可存取新增魚類頁面', function () {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/fish/create');
        $response->assertStatus(200);
    });

    it('登入後可存取照片管理頁面', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create();
        Http::fake(['*' => Http::response('', 404)]);
        
        $response = $this->actingAs($user)->get("/fish/{$fish->id}/media-manager");
        $response->assertStatus(200);
    });

    it('登入後可存取知識管理頁面', function () {
        $user = User::factory()->create();
        $fish = Fish::factory()->create();
        Http::fake(['*' => Http::response('', 404)]);
        
        $response = $this->actingAs($user)->get("/fish/{$fish->id}/knowledge-manager");
        $response->assertStatus(200);
    });
});
