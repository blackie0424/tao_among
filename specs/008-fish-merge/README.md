# 魚類合併功能規格文件

## 📋 功能概述

提供合併重複魚類的功能，將多條實際為同一種魚但因拍攝角度不同而被重複新增的資料合併為單一魚類。

## 🎯 合併策略

### 情境 A：無衝突資料（全部合併）
當被併入魚類的資料與主魚類無衝突時，所有資料直接轉移：
- ✅ 筆記 (fish_notes) - 全部轉移
- ✅ 發音 (fish_audios) - 全部轉移
- ✅ 捕獲紀錄 (capture_records) - 全部轉移
- ✅ 部落分類 (tribal_classifications) - 無衝突的全部轉移

### 情境 B：部落分類衝突（保留主魚類）
當主魚類與被併入魚類都有同一個部落的分類資料時：
- ✅ **保留主魚類的資料**
- ❌ 刪除被併入魚類的衝突資料
- 📝 理由：主魚類通常是最早建立且資料較完整的

**範例：**
```
主魚類 #123 - Ivalino: oyod | 去魚鱗 | 族人常吃
被併入 #789 - Ivalino: rahet | 剝皮 | 偶爾捕獲

合併後 → 保留 #123 的 Ivalino 資料
```

### 情境 C：尺寸衝突（智慧選擇）
魚類尺寸為 1:1 關聯，採用智慧選擇策略：
- ✅ 主魚類有尺寸 → **保留主魚類**的尺寸
- ✅ 主魚類無尺寸 → **轉移被併入魚類**的尺寸

## 🔧 API 規格

### 1. 預覽合併 (Preview)

```http
POST /prefix/api/fish/merge/preview
Content-Type: application/json

{
  "target_fish_id": 123,
  "source_fish_ids": [456, 789]
}
```

**回應：**
```json
{
  "success": true,
  "message": "預覽成功",
  "data": {
    "target": { /* 主魚類完整資料 */ },
    "sources": [ /* 被併入魚類陣列 */ ],
    "conflicts": {
      "tribal_classifications": [
        {
          "tribe": "ivalino",
          "source_fish_id": 789,
          "target_data": { /* 主魚類該部落資料 */ },
          "source_data": { /* 被併入魚類該部落資料 */ },
          "resolution": "keep_target"
        }
      ],
      "fish_size": [
        {
          "source_fish_id": 789,
          "target_exists": true,
          "source_exists": true,
          "resolution": "keep_target"
        }
      ]
    },
    "summary": {
      "notes_to_transfer": 3,
      "audios_to_transfer": 2,
      "records_to_transfer": 5,
      "classifications_to_transfer": 2,
      "classifications_conflicts": 1
    }
  }
}
```

### 2. 執行合併 (Merge)

```http
POST /prefix/api/fish/merge
Content-Type: application/json

{
  "target_fish_id": 123,
  "source_fish_ids": [456, 789]
}
```

**回應：**
```json
{
  "success": true,
  "message": "合併成功",
  "data": {
    "target_fish_id": 123,
    "merged_fish_ids": [456, 789],
    "transferred": {
      "notes": 3,
      "audios": 2,
      "capture_records": 5,
      "tribal_classifications": 2,
      "fish_size": false
    },
    "conflicts_resolved": {
      "tribal_classifications": 1,
      "fish_size": 1
    }
  }
}
```

## 🛡️ 資料完整性保護

### Transaction 保護
所有合併操作都包在資料庫 Transaction 中：
- ✅ 任何步驟失敗會自動 rollback
- ✅ 確保資料一致性
- ✅ 不會產生部分合併的情況

### 軟刪除
被併入的魚類使用軟刪除：
- ✅ 資料不會永久消失
- ✅ 可追溯合併歷史
- ✅ 必要時可恢復

### 驗證機制
- ✅ 目標魚類與來源魚類必須存在
- ✅ 無法將魚類合併到自己
- ✅ 至少需要一條被併入的魚類
- ✅ 所有 ID 必須為有效的整數

## 📊 測試涵蓋率

共 13 個測試案例，涵蓋：
1. ✅ 基礎驗證（6 個測試）
2. ✅ 無衝突合併（1 個測試）
3. ✅ 部落分類衝突處理（1 個測試）
4. ✅ 尺寸衝突處理（2 個測試）
5. ✅ 預覽功能（1 個測試）
6. ✅ 批次合併（1 個測試）
7. ✅ Transaction rollback（1 個測試）

所有測試通過率：**100%** (318/318 Feature tests)

## 🚀 使用範例

### 基本合併流程

```php
// 1. 先預覽
$preview = $fishMergeService->previewMerge(123, [456, 789]);

// 2. 檢查衝突
if (!empty($preview['conflicts'])) {
    // 顯示衝突資訊給使用者確認
}

// 3. 執行合併
$result = $fishMergeService->mergeFish(123, [456, 789]);

// 4. 顯示結果
echo "已轉移 {$result['transferred']['notes']} 筆筆記";
echo "已解決 {$result['conflicts_resolved']['tribal_classifications']} 個部落分類衝突";
```

### 前端呼叫範例

```javascript
// 預覽
const preview = await fetch('/prefix/api/fish/merge/preview', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    target_fish_id: 123,
    source_fish_ids: [456, 789]
  })
});

// 執行合併
const result = await fetch('/prefix/api/fish/merge', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    target_fish_id: 123,
    source_fish_ids: [456, 789]
  })
});
```

## 📝 注意事項

1. **不可逆操作**：合併後無法自動復原，建議使用預覽功能確認
2. **衝突處理**：預設保留主魚類資料，被併入的衝突資料會被刪除
3. **批次合併**：可一次合併多條魚類，但建議分批處理避免超時
4. **權限控制**：未來可能需要加入管理員權限驗證

## 🔄 未來優化方向

- [ ] 前端 UI 介面實作
- [ ] 合併歷史追蹤（fish_merges 資料表）
- [ ] 取消合併功能
- [ ] 權限控制（僅管理員可合併）
- [ ] 智慧推薦重複魚類（AI/ML）
- [ ] 批次合併優化（背景任務）

## 📚 相關檔案

- **Service**: `app/Services/FishMergeService.php`
- **Controller**: `app/Http/Controllers/FishMergeController.php`
- **Request**: `app/Http/Requests/MergeFishRequest.php`
- **Routes**: `routes/api.php`
- **Tests**: `tests/Feature/FishMergeTest.php`

---

**版本**: 1.0.0  
**建立日期**: 2025-12-30  
**最後更新**: 2025-12-30
