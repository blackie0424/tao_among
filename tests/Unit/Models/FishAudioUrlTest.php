<?php

use App\Models\Fish;
use App\Services\SupabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Fish Model audio_url Accessor 測試
 *
 * 測試目標：確保 audio_url accessor 在各種情境下都能正確處理
 * - 情境 A：新增魚類時，audio_filename 欄位不存在
 * - 情境 B：audio_filename 存在但為 null
 * - 情境 C：audio_filename 存在且有值
 */

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // 不需要 mock，使用真實的 SupabaseStorageService
    // .env.testing 中已設定測試用的 Supabase 配置
});

// 測試情境 A：新增魚類時，audio_filename 欄位不存在
it('audio_url 在 audio_filename 鍵不存在時回傳 null', function () {
    // 建立魚類，不包含 audio_filename
    $fish = Fish::create([
        'name' => '測試魚類',
        'image' => 'test-image.jpg',
    ]);

    // 重新查詢以確保從資料庫載入
    $fish = Fish::find($fish->id);

    // 驗證：audio_url 應該回傳 null
    expect($fish->audio_url)->toBeNull();
    
    // 驗證：toArray() 不應拋出錯誤
    $fishArray = $fish->toArray();
    expect($fishArray)->toHaveKey('audio_url');
    expect($fishArray['audio_url'])->toBeNull();
});

// 測試情境 B：audio_filename 存在但值為 null
it('audio_url 在 audio_filename 明確為 null 時回傳 null', function () {
    // 建立魚類，明確設定 audio_filename 為 null
    $fish = Fish::create([
        'name' => '測試魚類',
        'image' => 'test-image.jpg',
        'audio_filename' => null,
    ]);

    // 重新查詢
    $fish = Fish::find($fish->id);

    // 驗證：audio_url 應該回傳 null
    expect($fish->audio_url)->toBeNull();
    
    // 驗證：toArray() 正常運作
    $fishArray = $fish->toArray();
    expect($fishArray['audio_url'])->toBeNull();
});

// 測試情境 C：audio_filename 存在且有值
it('audio_url 在 audio_filename 存在時回傳完整 URL', function () {
    // 建立魚類，包含 audio_filename
    $fish = Fish::create([
        'name' => '測試魚類',
        'image' => 'test-image.jpg',
        'audio_filename' => 'test-audio.mp3',
    ]);

    // 重新查詢
    $fish = Fish::find($fish->id);

    // 驗證：audio_url 應該回傳完整 URL（包含音檔名稱）
    expect($fish->audio_url)
        ->not->toBeNull()
        ->toContain('test-audio.mp3') // 必須包含檔案名稱
        ->toBeString(); // 確保是字串
});

// 測試情境 D：更新 audio_filename 從無到有
it('audio_url 在新增 audio_filename 後正確更新', function () {
    // 建立魚類，不包含 audio_filename
    $fish = Fish::create([
        'name' => '測試魚類',
        'image' => 'test-image.jpg',
    ]);

    // 初始狀態：audio_url 為 null
    expect($fish->audio_url)->toBeNull();

    // 更新：加入 audio_filename
    $fish->update(['audio_filename' => 'test-audio.mp3']);
    $fish->refresh();

    // 驗證：audio_url 現在應該有值
    expect($fish->audio_url)
        ->not->toBeNull()
        ->toContain('test-audio.mp3');
});

// 測試情境 E：更新 audio_filename 從有到無
it('audio_url 在移除 audio_filename 後正確更新', function () {
    // 建立魚類，包含 audio_filename
    $fish = Fish::create([
        'name' => '測試魚類',
        'image' => 'test-image.jpg',
        'audio_filename' => 'test-audio.mp3',
    ]);

    // 初始狀態：audio_url 有值
    expect($fish->audio_url)->not->toBeNull();

    // 更新：移除 audio_filename（設為 null）
    $fish->update(['audio_filename' => null]);
    $fish->refresh();

    // 驗證：audio_url 現在應該為 null
    expect($fish->audio_url)->toBeNull();
});

// 測試情境 F：確保 Log::info 中的 toArray() 不會失敗
it('toArray() 在 logging 情境下不會失敗', function () {
    // 模擬 FishController::store() 中的情境
    $fish = Fish::create([
        'name' => '新增魚類',
        'image' => 'new-fish.jpg',
    ]);

    // 驗證：模擬 Log 中使用 toArray()
    $logData = [
        '請求資料' => ['name' => '新增魚類', 'image' => 'new-fish.jpg'],
        '建立的魚類ID' => $fish->id,
        '魚類完整資料' => $fish->toArray(), // 這裡不應拋出錯誤
        '魚類屬性' => $fish->getAttributes(),
    ];
    
    expect($logData)->toBeArray();
    expect($logData)->toHaveKey('魚類完整資料');
    expect($logData['魚類完整資料'])->toHaveKey('audio_url');
});
