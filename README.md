# Tao Among 專案

本專案為基於 Laravel 的魚類資料管理 API，支援 RESTful 操作、驗證、測試與自動化部署。

## 主要功能

- 魚類資料 CRUD（建立、查詢、更新、刪除）
- 魚類筆記管理
- 圖片上傳
- 完整 API 驗證（含自訂 Request 驗證）
- Pest 驗證測試案例
- 支援 Vercel 雲端部署

## 專案結構簡介

- `app/Http/Controllers/`：控制器（如 FishController，負責 API 邏輯）
- `app/Models/`：Eloquent ORM 資料模型
- `app/Http/Requests/`：表單驗證（如 CreateFishRequest、UpdateFishRequest）
- `routes/api.php`：API 路由設定
- `tests/Feature/`：功能測試（Pest 語法）
- `resources/views/`：Blade 前端模板
- `public/`：靜態資源與入口
- 其他：設定檔、資料庫 migration、CI/CD 等

## API 範例

- 取得魚類列表：`GET /prefix/api/fish`
- 新增魚類：`POST /prefix/api/fish`
- 更新魚類：`PUT /prefix/api/fish/{id}`
- 取得單一魚類：`GET /prefix/api/fish/{id}`
- 上傳圖片：`POST /prefix/api/upload`

## 測試案例說明

本專案使用 Pest 撰寫測試，涵蓋：

- 正常取得、建立、更新魚類資料
- 更新不存在資料時回傳 404
- 欄位驗證失敗時回傳 422（如 name 為空、型別錯誤、長度超過 255）
- since 參數錯誤時回傳 400
- 空資料、資料庫為空等情境

執行測試：
```sh
./vendor/bin/pest