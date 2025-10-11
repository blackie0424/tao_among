# 重構待辦事項

## 混合 fetch 和 Inertia.js 的問題

### 問題描述

目前 EditFishSize 和 EditFishName 使用了不一致的實作方式，混合了 fetch API 和 Inertia.js，這可能導致：

- iframe 視窗問題（類似捕獲紀錄更新的問題）
- 不一致的用戶體驗
- 維護困難

### 受影響的文件

1. **resources/js/Pages/EditFishSize.vue**

   - 使用 `fetch()` API 調用 `/prefix/api/fish/{id}/editSize`
   - 成功後使用 `router.visit()` 導航

2. **resources/js/Components/FishNameForm.vue**
   - 使用 `fetch()` API 調用 `/prefix/api/fish/{id}`
   - 成功後 emit 事件，父組件使用 `router.visit()` 導航

### 正確的參考實作

- **EditTribalClassification** 使用純 Inertia.js 方式：
  - 前端使用 `router.put()`
  - 後端返回 `redirect()` 響應
  - 無 iframe 視窗問題

### 重構計劃

1. 修正 EditFishSize：

   - 將 `fetch()` 改為 `router.put()`
   - 確保後端路由和控制器方法正確處理

2. 修正 FishNameForm：

   - 將 `fetch()` 改為 `router.put()`
   - 統一錯誤處理方式

3. 後端 API 路由清理：
   - 移除不必要的 API 路由（如 `/prefix/api/fish/{id}/editSize`）
   - 統一使用 web 路由

### 優先級

- 中等優先級
- 可在下次維護週期處理
- 不影響核心功能，但影響代碼一致性

### 相關 Commit

- e0e3e96: 修正捕獲紀錄更新時的 iframe 視窗問題（已解決的參考案例）

---

_記錄日期: 2025-01-12_
_記錄者: Kiro AI Assistant_
