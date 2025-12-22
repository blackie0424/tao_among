# Fish Model 單元測試

## FishAudioUrlTest.php

測試 Fish Model 的 `audio_url` accessor 在各種情境下的行為。

### 測試覆蓋情境

1. **情境 A**：新增魚類時，`audio_filename` 欄位不存在
2. **情境 B**：`audio_filename` 存在但值為 `null`
3. **情境 C**：`audio_filename` 存在且有值
4. **情境 D**：更新 `audio_filename` 從無到有
5. **情境 E**：更新 `audio_filename` 從有到無
6. **情境 F**：集合操作中的 accessor 行為
7. **情境 G**：`toArray()` 在 logging 情境下的行為

### 執行測試

```bash
# 執行所有 Fish Model 測試
./vendor/bin/pest tests/Unit/Models/FishAudioUrlTest.php

# 執行特定測試
./vendor/bin/pest tests/Unit/Models/FishAudioUrlTest.php --filter="audio_url 在 audio_filename 鍵不存在時回傳 null"
```

### 測試要點

- 使用 `RefreshDatabase` trait 確保測試間的資料隔離
- 測試涵蓋直接屬性存取與 `toArray()` 序列化兩種情境
- 驗證 accessor 正確處理 `isset()` 檢查，避免 "Undefined array key" 錯誤

### 相關修正

此測試套件對應 Fish Model 中的 `audioUrl()` accessor 修正：

```php
protected function audioUrl(): Attribute
{
    return Attribute::make(
        get: function ($value, $attributes) {
            // 修正：加入 isset() 檢查
            if (!isset($attributes['audio_filename']) || $attributes['audio_filename'] === null) {
                return null;
            }
            
            return app(SupabaseStorageService::class)->getUrl('audios', $attributes['audio_filename'], null);
        }
    );
}
```

修正前的問題：當呼叫 `$fish->toArray()` 時（例如在 Log::info() 中），Laravel 會嘗試序列化所有 accessor。如果 `audio_filename` 鍵不存在，直接存取 `$attributes['audio_filename']` 會拋出 "Undefined array key" 錯誤。

修正後：使用 `isset()` 檢查確保鍵存在後才存取，避免錯誤發生。
