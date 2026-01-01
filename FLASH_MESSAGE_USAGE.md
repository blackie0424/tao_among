# FlashMessage 元件使用說明

## 已完成的設定

### 1. FlashMessage 元件
- 位置：`resources/js/Components/FlashMessage.vue`
- 功能：全局顯示成功、錯誤、資訊訊息
- 特性：
  - 自動 5 秒後消失
  - 可手動點擊 × 關閉
  - 支援動畫效果
  - 響應式設計

### 2. AppLayout
- 位置：`resources/js/Layouts/AppLayout.vue`
- 已整合 FlashMessage 元件
- 所有頁面預設使用此 Layout

### 3. Inertia Middleware
- 位置：`app/Http/Middleware/HandleInertiaRequests.php`
- 自動共享 flash messages 到所有頁面
- 支援的訊息類型：
  - `success`：成功訊息（綠色）
  - `error`：錯誤訊息（紅色）
  - `info`：資訊訊息（藍色）

### 4. 已註冊到 bootstrap/app.php
- Middleware 已自動載入到所有 web routes

## 後端使用方式

### 成功訊息
```php
return redirect('/fishs')->with('success', '魚類「鮪魚」已成功刪除！');
```

### 錯誤訊息
```php
return back()->with('error', '刪除失敗：權限不足');
```

### 資訊訊息
```php
return redirect('/dashboard')->with('info', '資料已更新，請稍後查看');
```

## 當前刪除流程

1. 使用者在 fish 頁面點擊刪除
2. 顯示確認對話框
3. 發送 DELETE 請求到後端
4. 後端執行刪除並 redirect 到 `/fishs`
5. **FlashMessage 自動在列表頁顯示成功訊息**
6. 5 秒後自動消失（或使用者手動關閉）

## 測試方式

### 測試成功流程
1. 進入任一魚類詳細頁（如 `/fish/1`）
2. 點擊右上角的 ⋮ 選單
3. 點擊「刪除」
4. 確認刪除
5. 應該會跳轉到 `/fishs` 並看到綠色成功訊息

### 測試錯誤流程
如果要測試錯誤訊息，可以暫時修改 `FishController@destroy`：

```php
public function destroy($id)
{
    // 測試用：強制錯誤
    return back()->with('error', '測試錯誤訊息');
    
    // ... 原本的程式碼
}
```

## 常見問題

### Q: 訊息沒有顯示？
A: 確認以下步驟：
1. 執行 `npm run build` 重新編譯
2. 清除瀏覽器快取
3. 檢查 `bootstrap/app.php` 是否已註冊 middleware
4. 檢查後端是否使用 `->with('success', ...)` 回傳

### Q: 訊息顯示位置不對？
A: FlashMessage 使用 `teleport to="body"`，會自動出現在右上角
如需調整位置，修改 `FlashMessage.vue` 中的 `top-4 right-4` class

### Q: 想要不同的自動消失時間？
A: 修改 `FlashMessage.vue` 中的 `setTimeout` 數值（目前是 5000 毫秒）

## 其他控制器範例

### FishNoteController
```php
public function destroy($fishId, $noteId)
{
    // ... 刪除邏輯
    return redirect()->route('fish.show', $fishId)
        ->with('success', '筆記已刪除');
}
```

### CaptureRecordController
```php
public function update(Request $request, $fishId, $recordId)
{
    // ... 更新邏輯
    return back()->with('success', '捕獲紀錄已更新');
}
```

## 進階使用：同時顯示多個訊息

目前 FlashMessage 支援同時顯示成功、錯誤、資訊訊息：

```php
return redirect('/fishs')
    ->with('success', '主要操作成功')
    ->with('info', '某些次要操作需要稍後處理');
```

這樣會同時顯示綠色和藍色兩個訊息框。
