<?php

use App\Models\Fish;
use App\Models\FishNote;
use App\Models\TribalClassification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// =====================================================
// API 公開路由（不需登入）
// =====================================================

describe('API 公開路由（不需登入）', function () {

    it('可公開取得魚類列表', function () {
        $response = $this->getJson('/prefix/api/fish');
        $response->assertStatus(200);
    });

    it('可公開取得單一魚類', function () {
        $fish = Fish::factory()->create();
        $response = $this->getJson("/prefix/api/fish/{$fish->id}");
        $response->assertStatus(200);
    });

    it('可公開搜尋魚類', function () {
        $response = $this->getJson('/prefix/api/fishs/search?q=test');
        $response->assertStatus(200);
    });

    it('健康檢查可公開存取', function () {
        $response = $this->getJson('/prefix/api/health-check');
        $response->assertStatus(200);
    });
});

// =====================================================
// API 寫入路由（未登入應回傳 401）
// =====================================================

describe('API 寫入路由（未登入應回傳 401）', function () {

    it('未登入無法新增魚類', function () {
        $response = $this->postJson('/prefix/api/fish', ['name' => 'Test Fish']);
        $response->assertStatus(401);
    });

    it('未登入無法更新魚類', function () {
        $fish = Fish::factory()->create();
        $response = $this->putJson("/prefix/api/fish/{$fish->id}", ['name' => 'New Name']);
        $response->assertStatus(401);
    });

    it('未登入無法刪除魚類', function () {
        $fish = Fish::factory()->create();
        $response = $this->deleteJson("/prefix/api/fish/{$fish->id}");
        $response->assertStatus(401);
    });

    it('未登入無法新增知識筆記', function () {
        $fish = Fish::factory()->create();
        $response = $this->postJson("/prefix/api/fish/{$fish->id}/note", ['content' => 'Test']);
        $response->assertStatus(401);
    });

    it('未登入無法更新知識筆記', function () {
        $fish = Fish::factory()->create();
        $note = FishNote::factory()->create(['fish_id' => $fish->id]);
        $response = $this->putJson("/prefix/api/fish/{$fish->id}/note/{$note->id}", ['content' => 'New']);
        $response->assertStatus(401);
    });

    it('未登入無法刪除知識筆記', function () {
        $fish = Fish::factory()->create();
        $note = FishNote::factory()->create(['fish_id' => $fish->id]);
        $response = $this->deleteJson("/prefix/api/fish/{$fish->id}/note/{$note->id}");
        $response->assertStatus(401);
    });

    it('未登入無法新增部落分類', function () {
        $fish = Fish::factory()->create();
        $response = $this->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", []);
        $response->assertStatus(401);
    });

    it('未登入無法更新部落分類', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create(['fish_id' => $fish->id]);
        $response = $this->putJson("/prefix/api/tribal-classifications/{$classification->id}", []);
        $response->assertStatus(401);
    });

    it('未登入無法刪除部落分類', function () {
        $fish = Fish::factory()->create();
        $classification = TribalClassification::factory()->create(['fish_id' => $fish->id]);
        $response = $this->deleteJson("/prefix/api/tribal-classifications/{$classification->id}");
        $response->assertStatus(401);
    });

    it('未登入無法合併魚類', function () {
        $response = $this->postJson('/prefix/api/fish/merge', []);
        $response->assertStatus(401);
    });

    it('未登入無法預覽合併魚類', function () {
        $response = $this->postJson('/prefix/api/fish/merge/preview', []);
        $response->assertStatus(401);
    });

    it('未登入無法上傳圖片', function () {
        $response = $this->postJson('/prefix/api/upload', []);
        $response->assertStatus(401);
    });

    it('未登入無法上傳音訊', function () {
        $response = $this->postJson('/prefix/api/upload-audio', []);
        $response->assertStatus(401);
    });

    it('未登入無法取得圖片 signed URL', function () {
        $response = $this->postJson('/prefix/api/storage/signed-upload-url', []);
        $response->assertStatus(401);
    });

    it('未登入無法取得音訊 signed URL', function () {
        $fish = Fish::factory()->create();
        $response = $this->postJson("/prefix/api/fish/{$fish->id}/storage/signed-upload-audio-url", []);
        $response->assertStatus(401);
    });

    it('未登入無法簽署待處理音訊', function () {
        $response = $this->postJson('/prefix/api/upload/audio/sign', []);
        $response->assertStatus(401);
    });

    // 模擬 browser fetch（無 Accept: application/json）
    it('未登入 + 無 Accept 標頭：POST /upload 回傳 JSON 401 而非 HTML 重導向', function () {
        $response = $this->post('/prefix/api/upload', []);
        $response->assertStatus(401);
        expect($response->getContent())->not->toContain('<!DOCTYPE');
    });

    it('未登入 + 無 Accept 標頭：POST /storage/signed-upload-url 回傳 JSON 401 而非 HTML 重導向', function () {
        $response = $this->post('/prefix/api/storage/signed-upload-url', ['filename' => 'test.jpg']);
        $response->assertStatus(401);
        expect($response->getContent())->not->toContain('<!DOCTYPE');
    });
});

// =====================================================
// /schedule-run 路由應已移除
// =====================================================

describe('/schedule-run 路由已移除', function () {

    it('schedule-run 路由不存在，應回傳 404', function () {
        $response = $this->getJson('/prefix/api/schedule-run');
        $response->assertStatus(404);
    });
});

// =====================================================
// 登入後可存取寫入路由
// =====================================================

describe('登入後可存取 API 寫入路由', function () {

    it('登入後可呼叫新增魚類（驗證通過認證層）', function () {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/prefix/api/fish', []);
        // 422 代表通過認證但資料驗證失敗，確認認證層已放行
        $response->assertStatus(422);
    });

    it('登入後可呼叫合併預覽（驗證通過認證層）', function () {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/prefix/api/fish/merge/preview', []);
        $response->assertStatus(422);
    });
});

// =====================================================
// viewer 角色應被拒絕（403）
// =====================================================

describe('viewer 角色無法存取寫入路由（應回傳 403）', function () {

    it('viewer 無法新增魚類', function () {
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->postJson('/prefix/api/fish', ['name' => 'test', 'image' => 'test.jpg']);
        $response->assertStatus(403);
    });

    it('viewer 無法更新魚類', function () {
        $fish = Fish::factory()->create();
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->putJson("/prefix/api/fish/{$fish->id}", ['name' => 'hack']);
        $response->assertStatus(403);
    });

    it('viewer 無法刪除魚類', function () {
        $fish = Fish::factory()->create();
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->deleteJson("/prefix/api/fish/{$fish->id}");
        $response->assertStatus(403);
    });

    it('viewer 無法取得圖片 signed URL', function () {
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->postJson('/prefix/api/storage/signed-upload-url', ['filename' => 'test.jpg']);
        $response->assertStatus(403);
    });

    it('viewer 無法上傳音訊', function () {
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->postJson('/prefix/api/upload-audio', []);
        $response->assertStatus(403);
    });

    it('viewer 無法合併魚類', function () {
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->postJson('/prefix/api/fish/merge', []);
        $response->assertStatus(403);
    });

    it('viewer 無法新增知識筆記', function () {
        $fish = Fish::factory()->create();
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->postJson("/prefix/api/fish/{$fish->id}/note", ['note' => 'test']);
        $response->assertStatus(403);
    });

    it('viewer 無法新增部落分類', function () {
        $fish = Fish::factory()->create();
        $viewer = User::factory()->lineViewer()->create();
        $response = $this->actingAs($viewer)->postJson("/prefix/api/fish/{$fish->id}/tribal-classifications", []);
        $response->assertStatus(403);
    });
});

// =====================================================
// editor/admin 角色可放行
// =====================================================

describe('editor/admin 角色可存取寫入路由', function () {

    it('editor 可呼叫新增魚類（驗證通過角色層）', function () {
        $editor = User::factory()->lineEditor()->create();
        $response = $this->actingAs($editor)->postJson('/prefix/api/fish', []);
        // 422 代表通過認證與角色檢查，資料驗證失敗
        $response->assertStatus(422);
    });

    it('admin 可呼叫合併預覽（驗證通過角色層）', function () {
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->postJson('/prefix/api/fish/merge/preview', []);
        $response->assertStatus(422);
    });

    it('editor 可取得圖片 signed URL（驗證通過角色層）', function () {
        $editor = User::factory()->lineEditor()->create();
        $response = $this->actingAs($editor)->postJson('/prefix/api/storage/signed-upload-url', ['filename' => 'test.jpg']);
        // 非 403 代表角色層已放行（可能 400/422/200 視 service 行為而定）
        expect($response->status())->not->toBe(403);
    });
});
