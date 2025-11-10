# Tao Among Copilot Instructions

## 專案架構與開發重點

- **全端魚類資料管理系統**，後端採 Laravel (PHP) + PostgreSQL，前端為 Vue 3 SPA（Inertia.js），Tailwind CSS 主題，支援 Vercel 雲端部署。
- **API 設計**：RESTful，路由集中於 `routes/api.php`、`routes/web.php`，控制器於 `app/Http/Controllers/`，資料驗證於 `app/Http/Requests/`。
- **資料流**：前端透過 Inertia.js 呼叫 API，資料模型集中於 `app/Models/`，圖片/音檔上傳整合 Supabase。
- **元件化前端**：`resources/js/Components/` 為 UI 元件，`Pages/` 為頁面，`Tests/` 用 Vitest 撰寫單元測試。
- **測試**：後端用 Pest，前端用 Vitest，測試指令：`./vendor/bin/pest`、`npx vitest`。
- **樣式**：`resources/css/` 以 Tailwind 為主，`fish.css` 補充主題變數與自訂樣式。
- **CI/CD**：推送前請確保所有測試通過，CI 設定於 `.github/`。

## 關鍵開發流程

- **本地啟動**：`php artisan serve` 啟動後端，`npm run dev` 啟動前端。
- **測試**：後端 `./vendor/bin/pest`，前端 `npx vitest`。
- **部署**：推送 main 分支自動部署至 Vercel。
- **API 文件**：Swagger 設定於 `config/l5-swagger.php`，路徑 `/api/documentation`。

## 專案慣例與模式

- **元件命名**：UI 元件以功能命名（如 `FishNameForm.vue`），全域共用元件放於 `Components/Global/`。
- **Props/emit**：Vue 元件間資料傳遞嚴格用 props/emit，避免跨層 ref。
- **表單驗證**：後端集中於 `Requests/`，前端表單錯誤訊息統一顯示於元件內。
- **API 路徑**：所有 API 皆加 `/prefix/api/` 前綴，圖片上傳為 `/prefix/api/upload`。
- **主題切換**：深色/淺色主題以 Tailwind + CSS 變數實作，切換元件為 `DarkModeSwitcher.vue`。
- **資料視覺化**：如需圖表，統一用 Chart.js，元件化於 `Components/`。
- **測試覆蓋**：每個元件/頁面皆有對應 Vitest 測試，範例見 `Tests/TopNavBar.spec.js`。

## 重要檔案/目錄

- `app/Http/Controllers/`：API 控制器
- `app/Models/`：Eloquent ORM 資料模型
- `resources/js/Components/`：Vue 元件
- `resources/js/Pages/`：SPA 頁面
- `resources/js/Tests/`：Vitest 測試
- `resources/css/`：Tailwind 與主題 CSS
- `routes/`：API/Web 路由
- `tests/Feature/`：Pest 功能測試
- `.github/`：CI/CD 與 AI 指令

## 進階協作與注意事項

- **跨層溝通**：API schema 變更需同步更新前端型別與 Swagger 文件。
- **圖片/音檔上傳**：統一走 Supabase，相關服務於 `app/Services/UploadService.php`。
- **安全性**：API 驗證用 Laravel Sanctum，前端表單嚴格驗證，避免 XSS/CSRF。
- **效能**：避免 N+1 查詢，前端圖片 lazy loading，API 回應時間監控於 Vercel。
- **分支策略**：遵循 Git Flow，feature 分支合併前需通過所有測試。

---

如需更細節範例，請參考 `README.md` 及各目錄內註解。若有不明確處，請回報以利持續優化本指令。

## Active Technologies
- PHP 8.x（Laravel） + Laravel、Inertia.js（前端 SPA）、Pest（後端測試） (001-media-url-refactor)
- PostgreSQL、Supabase Storage（public bucket） (001-media-url-refactor)
- PHP 8.x（Laravel）, JavaScript/TypeScript（Vue 3 + Inertia.js） + Laravel Eloquent、Inertia.js、Tailwind CSS、Pest（後端測試）、Vitest（前端測試） (005-fishs-incremental-loading)
- PostgreSQL（prod），SQLite（tests） (005-fishs-incremental-loading)
- PHP 8.x（Laravel） + Laravel Framework（Eloquent、Validation、Routing）、Inertia.js（前端呼叫與渲染） (006-backend-search)

## Recent Changes
- 001-media-url-refactor: Added PHP 8.x（Laravel） + Laravel、Inertia.js（前端 SPA）、Pest（後端測試）
