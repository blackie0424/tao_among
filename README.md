# Tao Among — 魚類資料管理系統

原住民傳統漁業知識保存平台，結合田調人員的 LINE Bot 上傳流程與 Web 後台管理介面。

## 系統架構概覽

```
┌─────────────────────────────────────────────────────────┐
│                      使用者端                            │
│   Web Browser (管理後台)    LINE App (田調人員)          │
└──────────────┬──────────────────────┬───────────────────┘
               │ HTTPS                │ LINE Webhook
               ▼                      ▼
┌─────────────────────────────────────────────────────────┐
│               AWS EC2 (ap-southeast-1)                   │
│  ┌──────────────────────────────────────────────────┐   │
│  │  Nginx (反向代理)                                 │   │
│  │  ├── PHP-FPM (Laravel 11)                        │   │
│  │  │   ├── Inertia.js SPA (Vue 3)                  │   │
│  │  │   ├── RESTful API                             │   │
│  │  │   └── LINE Bot Webhook                        │   │
│  │  └── Static Assets (public/build/)               │   │
│  └──────────────────────────────────────────────────┘   │
│  ┌──────────────────────────────────────────────────┐   │
│  │  PostgreSQL (同機部署)                            │   │
│  └──────────────────────────────────────────────────┘   │
└──────────────────────────┬──────────────────────────────┘
                           │ S3 API
                           ▼
┌─────────────────────────────────────────────────────────┐
│                   AWS S3 Bucket                          │
│  images/   audio/   webp/                               │
└─────────────────────────────────────────────────────────┘
```

## 技術棧

| 層級      | 技術                                             |
| --------- | ------------------------------------------------ |
| 語言      | PHP 8.2+、JavaScript (ES2022)                    |
| 後端框架  | Laravel 11                                       |
| 前端框架  | Vue 3 + Inertia.js                               |
| 樣式      | Tailwind CSS 3                                   |
| 資料庫    | PostgreSQL（生產）、SQLite（測試）               |
| 檔案儲存  | AWS S3（`league/flysystem-aws-s3-v3`）           |
| 認證      | Laravel Sanctum + Session Guard                  |
| LINE 整合 | LINE Messaging API SDK (`linecorp/line-bot-sdk`) |
| API 文件  | Swagger（`darkaonline/l5-swagger`）              |
| 後端測試  | Pest 3                                           |
| 前端測試  | Vitest 3                                         |
| CI/CD     | GitHub Actions → rsync → EC2                     |

## 快速開始（本地開發）

### 前置需求

- PHP 8.2+（含 `pdo_pgsql`、`pdo_sqlite`、`gd` 擴充）
- Composer 2
- Node.js 20+
- PostgreSQL 或 SQLite（本地測試用 SQLite 即可）

### 安裝步驟

```bash
# 1. 安裝相依套件
composer install
npm install

# 2. 設定環境
cp .env.example .env
php artisan key:generate

# 3. 執行資料庫 migration
php artisan migrate --seed

# 4. 啟動服務
php artisan serve       # 後端：http://localhost:8000
npm run dev             # 前端（Vite HMR）
```

### 環境變數（重要）

```env
# 應用程式
APP_ENV=local
APP_URL=http://localhost:8000

# 資料庫
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=tao_among

# 檔案儲存（AWS S3）
STORAGE_DRIVER=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=
AWS_IMAGE_FOLDER=images
AWS_AUDIO_FOLDER=audio
AWS_WEBP_FOLDER=webp

# LINE Bot
LINE_CHANNEL_SECRET=
LINE_CHANNEL_ACCESS_TOKEN=
LINE_VIEWER_RICH_MENU_ID=
LINE_EDITOR_RICH_MENU_ID=
```

## 執行測試

```bash
# 後端（Pest）—— 使用 SQLite in-memory，不需要 PostgreSQL
./vendor/bin/pest --testsuite=Unit
./vendor/bin/pest --testsuite=Feature --exclude-group=legacy

# 前端（Vitest）
npx vitest run

# 含覆蓋率
npx vitest run --coverage
```

## 主要功能模組

### 魚類資料管理

- 魚類 CRUD（名稱、圖片、音檔、顯示圖）
- 捕獲紀錄管理（部落、地點、捕獲方式、日期）
- 地方知識（部落分類：食物類別、處理方式）
- 知識筆記（自由格式文字）
- 音訊管理（最長 5.1 秒 M4A/WebM）
- 魚類合併（將重複魚類合併）
- 圖片 WebP 自動轉換

### LINE Bot 整合

LINE Bot 供田調人員在外出田野時直接上傳影像與語音：

- **Webhook**：`POST /prefix/api/line/webhook`
- 使用者首次互動自動建立 `LineUser` 紀錄（viewer 角色）
- 依角色綁定不同「圖文選單」（Rich Menu）
  - `viewer`：一般瀏覽者，只能搜尋魚類
  - `editor`/`admin`：可上傳圖片與音檔
- 音訊格式：LINE 傳送 M4A（AAC），上傳 S3 時設定 `ContentType: audio/mp4`

### 媒體檔案儲存（AWS S3）

所有圖片與音訊透過 `StorageServiceInterface` 抽象操作：

```
S3 Bucket
├── images/   原始 JPEG/PNG 圖片
├── webp/     WebP 壓縮版本（CheckFishWebp Artisan 指令批次轉換）
└── audio/    M4A 音訊檔案
```

前端上傳採用「Presigned URL」機制，瀏覽器直傳 S3，不佔用伺服器頻寬。

## 目錄結構

```
app/
├── Console/Commands/       Artisan 指令
│   ├── CheckFishWebp           批次轉換圖片為 WebP
│   ├── SetupRichMenuCommand    設定 LINE 圖文選單
│   └── PurgePendingAudio       清除待確認音檔
├── Contracts/              介面定義（StorageServiceInterface 等）
├── Http/
│   ├── Controllers/        控制器
│   ├── Middleware/
│   └── Requests/           表單驗證 Request 類別
├── Models/                 Eloquent 資料模型
└── Services/               業務邏輯服務層
    ├── S3StorageService        AWS S3 儲存實作
    ├── LineBotService          LINE Messaging API
    ├── LineUploadService       LINE 媒體上傳
    ├── FishService             魚類業務邏輯
    └── FishMergeService        魚類合併邏輯

resources/js/
├── Components/             Vue 共用元件
├── Pages/                  Inertia.js SPA 頁面
└── Tests/                  Vitest 前端測試

routes/
├── api.php                 RESTful API 路由（前綴 /prefix/api/）
└── web.php                 Web 路由（Inertia.js）

tests/
├── Feature/                Pest 功能測試
└── Unit/                   Pest 單元測試
```

## API 端點概覽

所有 API 加 `/prefix/api/` 前綴，完整文件見 `/api/documentation`（Swagger UI）。

| 方法   | 路徑                         | 說明                         |
| ------ | ---------------------------- | ---------------------------- |
| GET    | `/fish`                      | 取得魚類列表（分頁）         |
| POST   | `/fish`                      | 新增魚類                     |
| GET    | `/fish/{id}`                 | 取得單一魚類                 |
| PUT    | `/fish/{id}`                 | 更新魚類                     |
| DELETE | `/fish/{id}`                 | 刪除魚類                     |
| GET    | `/fishs/search`              | 搜尋魚類                     |
| POST   | `/upload`                    | 上傳圖片（伺服器端）         |
| POST   | `/storage/signed-upload-url` | 取得 S3 Presigned Upload URL |
| POST   | `/fish/merge`                | 合併魚類                     |
| POST   | `/line/webhook`              | LINE Bot Webhook             |

## 開發慣例

- **分支策略**：Git Flow（`feature/*` → `develop` → `main`），push `main` 觸發自動部署
- **測試**：每個新功能需附帶對應測試；PR 需 CI 全綠才能合併
- **API 文件**：新增/修改 API 需同步更新 Swagger 標註
- **安全性**：禁止提交 `.env`、AWS 金鑰等敏感資訊
- **N+1 防範**：善用 Eloquent `with()` 預先載入關聯
- **儲存抽象**：所有檔案操作透過 `StorageServiceInterface`，不直接呼叫 `Storage::disk()`

## 參考文件

- [ARCHITECTURE.md](ARCHITECTURE.md) — 詳細系統架構與資料模型說明
- [DEPLOYMENT.md](DEPLOYMENT.md) — 部署流程與 EC2 維運指南
- `/api/documentation` — Swagger API 文件（需本地啟動）
- [specs/](specs/) — 功能規格文件
