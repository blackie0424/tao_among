# Tao Among — 專案說明

簡短概述

- 全端魚類資料管理系統。後端採 Laravel (PHP) + PostgreSQL，前端為 Vue 3 SPA（Inertia.js），Tailwind CSS 主題，支援 Vercel 雲端部署。
- 圖片/音檔採 Supabase 整合，上傳/處理工作建議放到佇列處理。

快速開始

1. 進入專案資料夾：
   ```bash
   cd tao_among
   ```
2. 本地啟動
   - 後端：`php artisan serve`
   - 前端：`npm run dev`
3. 測試
   - 後端（Pest）：`./vendor/bin/pest`
   - 前端（Vitest）：`npx vitest`

專案目標（Constitution）

- 以最小可行產品 (MVP) 為導向，優先交付可驗證與可測試的功能，避免過度設計。
- 追求高程式碼品質、可測試性與可維護性；所有外部資源呼叫需做型別與 null 檢查，避免把 null 傳給需要字串的介面。

speckit 與 AI 指令（使用方式）

- 斜線指令（在 Copilot Chat 或相容 AI 聊天介面使用）：
  - `/speckit.constitution` — 建立專案原則
  - `/speckit.specify` — 建立基線規格
  - `/speckit.plan` — 建立實作計畫
  - `/speckit.tasks` — 產生可執行任務
  - `/speckit.implement` — 執行實作
- 注意：不要在終端或 zsh 直接輸入這些斜線指令（會被視為檔案路徑）；應在 Copilot Chat 的輸入框中執行。

專案慣例與開發重點

- API 設計：RESTful，路由集中於 `routes/api.php`、`routes/web.php`。
- 控制器：`app/Http/Controllers/`；資料驗證集中於 `app/Http/Requests/`。
- 模型：Eloquent 模型放於 `app/Models/`。
- 圖片/音檔：上傳與存取統一走 Supabase，相關服務放在 `app/Services/`。
- 前端元件：`resources/js/Components/`（共用元件放 `Components/Global/`），頁面放 `resources/js/Pages/`。
- 樣式：Tailwind 為主，輔以 `resources/css/` 的專案自訂樣式。
- 測試：後端用 Pest、前端用 Vitest；每個重要功能或元件應有對應測試。

重要檔案／目錄（概覽）

- app/Http/Controllers/ — API 控制器
- app/Models/ — 資料模型
- app/Services/ — 服務層（包含 Supabase 整合）
- resources/js/Components/ — Vue 元件
- resources/js/Pages/ — SPA 頁面
- resources/js/Tests/ — Vitest 測試
- routes/ — API / Web 路由
- tests/Feature/ — Pest 功能測試
- .github/ — CI/CD 與 AI 指令設定

CI / 合併規則

- PR 必須通過 lint 與測試（CI 綠燈）才能合併。
- 建議在 CI 中加入基本 secret 掃描。
- 分支策略建議遵循 Git Flow：feature -> develop -> release -> main。

日誌與可觀察性

- 生產環境請使用平台日誌（例如設定 `.env`：`LOG_CHANNEL=errorlog`），避免在唯讀檔案系統寫入 `storage/logs`。
- 追蹤重要指標：錯誤率、延遲、佇列長度與失敗率；設定告警門檻。

安全性與敏感資訊

- 不要把憑證、auth token 或私密金鑰提交到版本庫。
- Agent 或 speckit 可能會在資料夾中產生暫存或敏感檔案，請檢查並視情況納入 `.gitignore`（例如 `.speckit/` 或 `.github/agents/`）。
- 若不慎推送敏感資訊，立即撤銷金鑰並使用工具（如 BFG、git filter-repo）清除歷史。

效能與最佳實務

- 避免 N+1 查詢，必要時使用 eager loading 與快取。
- 媒體檔案優先壓縮並使用 CDN，耗時處理放入佇列。
- API 回應僅包含必要欄位以降低 payload。

文件與 Swagger

- API 文件配置於 `config/l5-swagger.php`，預設路徑 `/api/documentation`。
- 請同步更新 API schema 以維持前後端一致性。

開發工作流程摘要

1. 取得原始碼並安裝相依套件：
   ```bash
   composer install
   npm install
   ```
2. 本地啟動：
   ```bash
   php artisan migrate --seed
   php artisan serve
   npm run dev
   ```
3. 執行測試：
   ```bash
   ./vendor/bin/pest
   npx vitest
   ```

貢獻指南（簡短）

- 新功能採 feature 分支策略，發 PR 到 develop。
- PR 需包含簡短說明、測試與必要的文件更新。
- 重構或大型變更需先與團隊討論，避免 overdesign。

聯絡與支援

- 若不確定是否應提交 speckit 產生的檔案，先審查是否包含敏感資訊；必要時加入 `.gitignore`。
- 需要我協助掃描或產生建議 `.gitignore` 條目時，請提出要求。

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
```

## 音訊上傳新流程（兩階段）

1) 取得簽名上傳網址（pending/audio/...） → 2) 前端以 PUT 上傳檔案 → 3) 呼叫 confirm API，後端確認檔案存在後搬移至 audio/... 並以交易式寫入 DB。若未呼叫 confirm，暫存檔將由排程依 TTL 自動清理；若 confirm 失敗且檔案已搬移，系統會嘗試補償刪除避免孤兒檔案。

Quickstart（後端 API 範例）

1. 取得簽名：POST /prefix/api/upload/audio/sign
   - 請求：{ "fish_id": 1, "ext": "webm" }
   - 回應：{ uploadUrl: 絕對 https URL, filePath: "pending/audio/...", expiresIn: 300 }

2. 前端以 PUT 將檔案傳至 uploadUrl（Content-Type 設定為實際 MIME）。

3. 確認：POST /prefix/api/upload/audio/confirm
   - 請求：{ "fish_id": 1, "filePath": "pending/audio/..." }
   - 回應（200）：{ url: 絕對公開網址, filename: 最終檔名, state: "confirmed" }

特性
- 冪等：重複呼叫 confirm（同 fish_id + filePath）一律回 200 並返回現況。
- 清理：`php artisan audio:purge-pending --ttl=3600` 可手動清理過期暫存檔，Kernel 已預設每小時排程依 `config/audio.php` 的 TTL 執行。
