# 實作計畫

- [x] 1. 建立資料庫結構和模型

  - 建立 tribal_classifications 資料表遷移檔案
  - 建立 capture_records 資料表遷移檔案
  - 建立 TribalClassification 模型和關聯
  - 建立 CaptureRecord 模型和關聯
  - 擴展 Fish 模型新增關聯方法
  - _需求: 1.1, 2.1, 3.1, 4.1_

- [x] 2. 實作部落分類 API 端點

  - [x] 2.1 建立 TribalClassificationController

    - 實作 index 方法（取得魚類部落分類）
    - 實作 store 方法（新增部落分類）
    - 實作 update 方法（更新部落分類）
    - 實作 destroy 方法（刪除部落分類）
    - _需求: 3.1, 4.1, 6.1_

  - [x] 2.2 建立表單驗證請求類別

    - 建立 TribalClassificationRequest 驗證類別
    - 定義驗證規則和錯誤訊息
    - _需求: 3.3, 4.4_

  - [x] 2.3 設定 API 路由
    - 新增部落分類相關路由
    - 設定路由群組和中介軟體
    - _需求: 3.1, 4.1_

- [x] 3. 實作捕獲紀錄 Inertia 功能

  - [x] 3.1 在 FishController 中新增捕獲紀錄方法

    - 實作 captureRecords 方法（捕獲紀錄檢視頁面）
    - 實作 createCaptureRecord 方法（新增捕獲紀錄頁面）
    - 實作 storeCaptureRecord 方法（儲存捕獲紀錄）
    - 實作 editCaptureRecord 方法（編輯捕獲紀錄頁面）
    - 實作 updateCaptureRecord 方法（更新捕獲紀錄）
    - 實作 destroyCaptureRecord 方法（刪除捕獲紀錄）
    - 設定對應的 Web 路由
    - _需求: 12.1, 13.1, 14.1_

  - [x] 3.2 建立捕獲紀錄表單驗證

    - 建立 CaptureRecordRequest 驗證類別
    - 處理圖片上傳驗證
    - _需求: 13.3, 13.5_

  - [x] 3.3 整合 Supabase 圖片上傳
    - 擴展現有的 SupabaseStorageService
    - 處理捕獲紀錄圖片上傳和刪除
    - _需求: 13.2, 14.2_

- [x] 4. 建立捕獲紀錄前端頁面和元件

  - [x] 4.1 建立捕獲紀錄檢視頁面

    - 建立 CaptureRecords.vue 頁面
    - 建立 CaptureRecordCard.vue 元件
    - 整合 BottomNavBar 和 FAB 按鈕
    - _需求: 12.1, 12.2, 12.3_

  - [x] 4.2 建立捕獲紀錄表單頁面
    - 建立 CreateCaptureRecord.vue 頁面
    - 建立 EditCaptureRecord.vue 頁面
    - 建立 CaptureRecordForm.vue 元件
    - 整合圖片上傳功能
    - _需求: 13.1, 13.2, 14.1, 14.2_

- [x] 5. 實作搜尋和篩選功能

  - [x] 5.1 擴展魚類搜尋功能

    - 新增部落篩選參數
    - 新增飲食分類篩選參數
    - 新增處理方式篩選參數
    - 新增捕獲地點篩選參數
    - _需求: 7.1, 8.1, 9.1, 15.1_

  - [x] 5.2 建立搜尋服務類別
    - 建立 FishSearchService 處理複雜搜尋邏輯
    - 實作查詢建構器方法
    - _需求: 7.1, 8.1, 9.1_

- [x] 6. 建立搜尋和篩選介面

  - [x] 6.1 建立 FilterPanel 元件

    - 實作部落篩選下拉選單
    - 實作飲食分類篩選下拉選單
    - 實作處理方式篩選下拉選單
    - 實作地點搜尋輸入框
    - _需求: 7.1, 8.1, 9.1, 15.1_

  - [x] 6.2 擴展 SearchResults 元件

    - 顯示搜尋結果中的部落資訊
    - 突出顯示符合條件的資訊
    - 處理無結果狀態
    - _需求: 7.2, 8.4, 15.3_

  - [x] 6.3 擴展 Fish/Search.vue 頁面
    - 整合 FilterPanel 和 SearchResults 元件
    - 實作即時搜尋功能
    - 處理搜尋條件變更
    - _需求: 7.4, 8.3, 9.4_

- [x] 7. 擴展現有魚類頁面

  - [x] 7.1 擴展 Fish/Show.vue 頁面

    - 新增部落分類資訊顯示區塊
    - 新增捕獲紀錄連結
    - 整合比較檢視功能
    - _需求: 1.1, 2.1, 5.1, 12.1_

  - [x] 7.2 新增 BottomNavBar 捕獲紀錄連結
    - 在 BottomNavBar 中新增捕獲紀錄按鈕
    - 實作當前頁面高亮功能
    - _需求: 12.1_

- [ ] 8. 實作批量編輯功能

  - [ ] 8.1 建立批量編輯功能

    - 實作 batchUpdateTribal 方法
    - 處理多筆資料同時更新
    - 實作錯誤處理和回滾機制
    - _需求: 16.1, 16.2_

  - [ ] 8.2 建立批量編輯前端介面
    - 實作多選魚類功能
    - 建立批量編輯表單
    - 處理批量操作結果顯示
    - _需求: 16.3, 16.4, 16.5_

- [ ] 9. 撰寫測試

  - [x] 9.1 撰寫後端單元測試

    - 測試 TribalClassification 模型關聯
    - 測試 CaptureRecord 模型關聯
    - 測試驗證規則
    - _需求: 所有後端功能_

  - [x] 9.2 撰寫後端功能測試

    - 測試部落分類 Inertia 端點
    - 測試捕獲紀錄 Inertia 端點
    - 測試搜尋功能
    - 測試批量編輯功能
    - _需求: 所有功能_

  - [x] 9.3 撰寫前端元件測試
    - 測試 ClassificationForm 元件
    - 測試 CaptureRecordForm 元件
    - 測試 FilterPanel 元件
    - 測試使用者互動
    - _需求: 所有前端元件_

- [ ] 10. 資料庫最佳化和部署準備

  - [ ] 10.1 建立資料庫索引

    - 在 fish_id, tribe 欄位建立索引
    - 建立搜尋常用欄位的複合索引
    - _需求: 效能最佳化_

  - [ ] 10.2 設定快取機制

    - 快取部落和分類選項列表
    - 快取常用搜尋結果
    - _需求: 效能最佳化_

  - [ ] 10.3 更新環境配置
    - 更新 Supabase 儲存桶設定
    - 設定檔案上傳限制
    - 新增必要的環境變數
    - _需求: 部署準備_
