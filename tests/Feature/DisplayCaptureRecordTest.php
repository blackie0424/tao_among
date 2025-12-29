<?php

use App\Models\Fish;
use App\Models\CaptureRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('圖鑑主圖選擇功能', function () {
    
    beforeEach(function () {
        // 建立測試用魚類
        $this->fish = Fish::factory()->create([
            'name' => '測試魚類',
            'image' => 'test-fish.jpg',
        ]);
        
        // 建立多筆捕獲紀錄
        $this->record1 = CaptureRecord::factory()->create([
            'fish_id' => $this->fish->id,
            'image_path' => 'capture-1.jpg',
            'tribe' => 'ivalino',
        ]);
        
        $this->record2 = CaptureRecord::factory()->create([
            'fish_id' => $this->fish->id,
            'image_path' => 'capture-2.jpg',
            'tribe' => 'iranmeilek',
        ]);
    });
    
    it('預設 display_capture_record_id 為 null', function () {
        expect($this->fish->display_capture_record_id)->toBeNull();
    });
    
    it('預設 display_image_url 使用魚類原始圖片', function () {
        expect($this->fish->display_image_url)->toBe($this->fish->image_url);
    });
    
    it('可以設定捕獲紀錄為圖鑑主圖', function () {
        $this->fish->update(['display_capture_record_id' => $this->record1->id]);
        $this->fish->refresh();
        
        expect($this->fish->display_capture_record_id)->toBe($this->record1->id);
        expect($this->fish->display_image_url)->toBe($this->record1->image_url);
    });
    
    it('可以在不同捕獲紀錄之間切換主圖', function () {
        // 設定為第一張
        $this->fish->update(['display_capture_record_id' => $this->record1->id]);
        $this->fish->refresh();
        expect($this->fish->display_image_url)->toBe($this->record1->image_url);
        
        // 切換為第二張
        $this->fish->update(['display_capture_record_id' => $this->record2->id]);
        $this->fish->refresh();
        expect($this->fish->display_image_url)->toBe($this->record2->image_url);
    });
    
    it('刪除捕獲紀錄時自動重設為 null (ON DELETE SET NULL)', function () {
        // 設定主圖
        $this->fish->update(['display_capture_record_id' => $this->record1->id]);
        $this->fish->refresh();
        expect($this->fish->display_capture_record_id)->toBe($this->record1->id);
        
        // 強制刪除捕獲紀錄
        $this->record1->forceDelete();
        $this->fish->refresh();
        
        // 驗證自動重設為 null
        expect($this->fish->display_capture_record_id)->toBeNull();
        expect($this->fish->display_image_url)->toBe($this->fish->image_url);
    });
    
    it('軟刪除捕獲紀錄時自動 fallback 到原始圖片', function () {
        // 設定主圖
        $this->fish->update(['display_capture_record_id' => $this->record1->id]);
        $this->fish->refresh();
        expect($this->fish->display_image_url)->toBe($this->record1->image_url);
        
        // 軟刪除捕獲紀錄
        $this->record1->delete();
        
        // 重新載入 fish（不包含軟刪除的關聯）
        $this->fish = Fish::find($this->fish->id);
        
        // display_capture_record_id 仍保留，但 display_image_url 自動 fallback
        expect($this->fish->display_capture_record_id)->toBe($this->record1->id);
        expect($this->fish->display_image_url)->toBe($this->fish->image_url);
    });
    
    it('恢復軟刪除後，display_image_url 重新指向捕獲紀錄圖片', function () {
        // 設定主圖並軟刪除
        $this->fish->update(['display_capture_record_id' => $this->record1->id]);
        $this->record1->delete();
        $this->fish->refresh();
        
        // 確認 fallback 運作
        expect($this->fish->display_image_url)->toBe($this->fish->image_url);
        
        // 恢復捕獲紀錄
        $this->record1->restore();
        $this->fish->refresh();
        
        // 確認重新指向捕獲紀錄圖片
        expect($this->fish->display_image_url)->toBe($this->record1->image_url);
    });
    
    it('displayCaptureRecord 關聯正常運作', function () {
        $this->fish->update(['display_capture_record_id' => $this->record1->id]);
        $this->fish->refresh();
        
        $relation = $this->fish->displayCaptureRecord;
        
        expect($relation)->not->toBeNull();
        expect($relation->id)->toBe($this->record1->id);
        expect($relation->image_path)->toBe('capture-1.jpg');
    });
});

describe('圖鑑主圖 API 端點', function () {
    
    beforeEach(function () {
        $this->fish = Fish::factory()->create(['name' => '測試魚類']);
        $this->record = CaptureRecord::factory()->create([
            'fish_id' => $this->fish->id,
            'tribe' => 'ivalino',
        ]);
    });
    
    it('可以透過 API 設定圖鑑主圖', function () {
        $response = $this->put("/fish/{$this->fish->id}/display-image", [
            'capture_record_id' => $this->record->id,
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('success', '已設定為圖鑑主圖');
        
        $this->fish->refresh();
        expect($this->fish->display_capture_record_id)->toBe($this->record->id);
    });
    
    it('不允許設定不屬於該魚類的捕獲紀錄', function () {
        $otherFish = Fish::factory()->create();
        $otherRecord = CaptureRecord::factory()->create([
            'fish_id' => $otherFish->id,
            'tribe' => 'ivalino',
        ]);
        
        $response = $this->put("/fish/{$this->fish->id}/display-image", [
            'capture_record_id' => $otherRecord->id,
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHasErrors('capture_record_id');
    });
    
    it('capture_record_id 為必填欄位', function () {
        $response = $this->put("/fish/{$this->fish->id}/display-image", []);
        
        $response->assertSessionHasErrors('capture_record_id');
    });
    
    it('capture_record_id 必須存在於資料庫', function () {
        $response = $this->put("/fish/{$this->fish->id}/display-image", [
            'capture_record_id' => 99999, // 不存在的 ID
        ]);
        
        $response->assertSessionHasErrors('capture_record_id');
    });
});
