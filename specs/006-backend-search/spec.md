# Feature Specification: Backend Server-Side Search

**Feature Branch**: `006-backend-search`  
**Created**: 2025-11-07  
**Status**: Draft  
**Input**: User description: "我們要更改方案，為了減低前端載具的負擔，我們將把搜尋的功能改回後端實作，前端則是負責顯示資料"

## User Scenarios & Testing _(mandatory)_

<!--
  IMPORTANT: User stories should be PRIORITIZED as user journeys ordered by importance.
  Each user story/journey must be INDEPENDENTLY TESTABLE - meaning if you implement just ONE of them,
  you should still have a viable MVP (Minimum Viable Product) that delivers value.

  Assign priorities (P1, P2, P3, etc.) to each story, where P1 is the most critical.
  Think of each story as a standalone slice of functionality that can be:
  - Developed independently
  - Tested independently
  - Deployed independently
  - Demonstrated to users independently
-->

### User Story 1 - 使用者可在單一欄位快速搜尋 (Priority: P1)

使用者在「魚類列表 /fishs」輸入名稱關鍵字後提交，系統於 1 秒內回傳符合條件的分頁結果（游標式），首屏顯示縮圖與基本文字，並可持續瀏覽下一批。

**Why this priority**: 名稱搜尋是最常用的進入點；先確保核心可用與效能符合行動裝置需求。

**Independent Test**: 僅啟用名稱關鍵字搜尋並測試：輸入字串 → 提交 → 觀察回應延遲、結果正確性與游標分頁是否正常。

**Acceptance Scenarios**:

1. Given 魚類列表頁初次載入（無搜尋條件），後端以 `per_page_default` 批次大小（預設 20）回傳首批資料，When 使用者於搜尋框輸入「abc」並提交，Then 系統於 ≤1s 回應第一批符合「abc」的資料與 `pageInfo.hasMore`、`nextCursor`。
2. Given 已取得第一批搜尋結果且 `hasMore=true`，When 使用者捲動到底觸發續載，Then 顯示載入狀態並追加下一批同一搜尋條件結果，排序穩定且無重排抖動。
3. Given 搜尋結果為空，When 使用者提交「zzzz」不存在的關鍵字，Then 顯示空狀態與「無相符結果」提示，不觸發續載。
4. Given 使用者瀏覽第二批後觸發續載，且請求回 422 `{ error: "INVALID_CURSOR" }`（例如 last_id 方向錯誤），When 前端接收錯誤，Then 於列表底部顯示非阻斷錯誤橫幅「載入更多失敗（參數已失效），重新開始？」與一個「重新開始」按鈕；When 使用者點擊按鈕，Then 清空 last_id 與分頁狀態並重新以原搜尋條件取首批。
5. Given 使用者在「名稱」欄位輸入「rahet 魚」並提交，Then 系統不會自動套用食物分類，僅以名稱模糊詞「rahet 魚」查詢，回傳分頁結果與 `pageInfo`。

---

### User Story 2 - 多條件後端複合搜尋 (Priority: P2)

使用者可同時使用多個條件（名稱、部落、捕獲地點、處理方式等）提交搜尋，後端組合條件回傳正確分頁結果，前端僅渲染，不做本地篩選整併。

**Why this priority**: 提升精確性與減少前端記憶體/CPU 負擔；以伺服器端集中處理可避免行動裝置卡頓。

**Independent Test**: 單一複合查詢（例如名稱 + 部落 + 捕獲地點）提交後，驗證結果集是否符合所有條件且游標/續載一致。

**Acceptance Scenarios**:

1. Given 使用者輸入名稱「ab」並選擇部落「iratay」，When 提交搜尋，Then 回傳同時符合名稱模糊與指定部落的第一批結果與 pageInfo。
2. Given 使用者選擇捕獲地點「harbor」與處理方式「去魚鱗」，When 提交，Then 回傳同時符合兩者的結果（模糊/like 規則），若無結果顯示空狀態提示並停止續載。
3. Given 提交包含 3 個條件且有結果，When 觸底續載，Then 使用相同組合條件取下一批，結果無遺漏或重複。

---

[Add more user stories as needed, each with an assigned priority]

### Edge Cases

- 無條件搜尋：回傳預設排序之首批資料；若資料量過大導致延遲，仍維持分頁 envelope（不切換成全量）。
  - 初次載入批次大小：必須使用 `per_page_default`（設定於 `config/fish_search.php`）決定回傳筆數以加快首屏渲染（不可回傳超出 perPage 的全量）。
- 空結果：回傳 `items=[]` 與 `pageInfo.hasMore=false`，前端顯示「無相符結果」。
- 參數缺失或非法：`perPage` 超界或負值 → 正常化為預設；`last_id` 非正整數或方向錯誤（違反排序不變性）→ 422 錯誤回傳（不回 fallback 全量）。
- 重複觸發續載：後端忽略併發相同游標請求（前端鎖定）；僅第一個成功結果採用。
- 超大量條件組合：若條件導致查詢計畫過重並超過 1000ms，仍回傳正常結果；本期不記錄慢查詢 Log（依 FR-011），後續可於程式碼預留註解掛鉤以利未來擴充。
- URL 手工修改不合法參數：安全回應 422 並提供錯誤訊息（不洩漏內部結構）。
- 未知關鍵字：於「名稱」或「地點」文字欄位中出現的任何詞彙，皆僅作為該欄位之 ILIKE 模糊比對，不會被解讀為其他欄位的結構化條件。

  （註）本期不支援自由輸入到結構化條件的「關鍵字字典」解析，因此不存在跨維度的關鍵字衝突行為。

## Requirements _(mandatory)_

<!--
  ACTION REQUIRED: The content in this section represents placeholders.
  Fill them out with the right functional requirements.
-->

### Functional Requirements

- **FR-001**: 系統必須於 `GET /fishs` 支援多條件後端搜尋（名稱模糊、部落、捕獲地點、捕獲方式、處理方式、食物分類），並與游標式分頁 Query 參數（`last_id`, `perPage`）共存；本期路由不加版本前綴（不使用 `/v1`）。路由註冊位置統一使用 `routes/web.php`（不再新增於 `routes/api.php`），前端以 Inertia.js 發送請求並處理回傳 JSON；端點公開存取（見 FR-015）。
- **FR-002**: 回應必須包含 `items` 與 `pageInfo: { hasMore, nextCursor }`；`items` 內每筆僅回傳精簡欄位：`id`, `name`, `image_url`（移除其它現有非必要欄位以降低 payload，支援 SC-004）。白名單鎖定：本期禁止任意擴充回應欄位；新增欄位須經 specs/OpenAPI 更新與測試審核（避免 payload 非預期膨脹）。
- **FR-003**: 名稱搜尋採模糊比對（ILIKE %term%，大小寫不敏感）；部落採「精準等值」但大小寫不敏感，比對策略：`LOWER(tribe_column) = LOWER(:tribe)`（不使用 `%` 或 `_` 通配，亦不允許模糊）；捕獲地點（capture_location）、捕獲方式（capture_method）與處理方式（processing_method）同樣採模糊 ILIKE（`%term%`，大小寫不敏感，與名稱一致）。
  - 文字欄位正規化：對 `name`, `capture_location`, `capture_method`, `processing_method` 先行 trim；若結果為空字串則忽略該條件（不產生 `ILIKE '%%'` 的全域匹配），以避免非必要的全表掃描與負載。
  - 僅輸入空白：若文字欄位（例如 name 或 capture_location）接收到僅含空白或換行的輸入，後端先 trim 後發現為空字串時，視為「未提供該條件」而忽略（不產生 `ILIKE '%%'`）。
- **FR-004**: 多條件組合時使用 AND 邏輯（所有條件需同時滿足）；空值條件忽略，不影響其他條件。
- **FR-005**: 排序穩定採 `id DESC`；游標以明碼 `last_id`（上一批最後一筆的主鍵 id）續載，查詢條件增加 `id < last_id`。未來若改為不透明包裝，不影響前端傳回方式（原樣帶回）。
- **FR-006**: 若 `last_id` 參數存在但非正整數或語意不合法（例如違反排序不變性：提供的 `last_id` 大於或等於前一批最後一筆），回傳 422（含錯誤訊息）；不得回退為全量查詢。若僅是指向空區段（例如 `last_id` 過大導致無資料可取），回 200 並回傳空集合與 `hasMore=false`。

#### Comparison Rules Matrix（大小寫不敏感與比對型態一覽）

| 欄位              | 比對型態             | 大小寫處理           | 範例輸入  | 對應 SQL 片段                                    | 範例行為                                         |
| ----------------- | -------------------- | -------------------- | --------- | ------------------------------------------------ | ------------------------------------------------ |
| name              | 模糊 ILIKE           | ILIKE 本身不分大小寫 | "SaRdInE" | `name ILIKE '%sardine%'`                         | 回傳含 sardine, Sardine, SARDINE 的所有符合項    |
| capture_location  | 模糊 ILIKE           | ILIKE                | "HarBor"  | `capture_location ILIKE '%harbor%'`              | 包含 Harbor, HARbor, sea-harbor 等含 harbor 片段 |
| capture_method    | 模糊 ILIKE           | ILIKE                | "Net"     | `capture_method ILIKE '%net%'`                   | 包含 net, casting net, drift NET                 |
| processing_method | 模糊 ILIKE           | ILIKE                | "Dry"     | `processing_method ILIKE '%dry%'`                | 包含 dry, sun-dry, DRYING（若需求保留前綴匹配）  |
| tribe             | 等值（大小寫不敏感） | `LOWER()` 雙邊       | "Iratay"  | `LOWER(tribe_column) = LOWER('Iratay')`          | 僅匹配 iratay / IRATAY 精準文字（不含部分匹配）  |
| food_category     | 等值（大小寫不敏感） | `LOWER()` 雙邊       | "Protein" | `LOWER(food_category_column) = LOWER('Protein')` | 僅匹配 Protein / protein；不接受部分字串         |

說明：

1. 模糊 ILIKE 欄位均採 `%term%` 模式；前後皆加通配符以支援子字串搜尋。
2. 等值欄位（tribe, food*category）禁止使用 `%`、`*` 通配符；若輸入包含此類字元，視為純文字不展開。
3. 前置 trim：`name`, `capture_location`, `capture_method`, `processing_method` 若經 trim 後為空字串 → 忽略該欄位（不產生 ILIKE '%%'）。
4. Size/大小寫：ILIKE 已天然大小寫不敏感；等值比對透過 `LOWER(column)=LOWER(:value)` 達成。
5. 安全與效能：對等值欄位避免模糊以防計畫膨脹；模糊欄位建議在資料庫對應欄位上建立普通索引或後續考慮 trigram/GIN（本期不實作）。
6. 若將來需要前綴限定（例如僅前綴匹配 `%term`），需新增規格條目，現行統一使用雙側通配提高使用者可發現性。

範例組合請求：

```
GET /fishs?name=SaRdInE&tribe=Iratay&capture_location=HarBor&capture_method=Net
```

對應 SQL（簡化示意）：

```
SELECT id,name,image_url
FROM fish
WHERE name ILIKE '%sardine%'
  AND LOWER(tribe_column)=LOWER('Iratay')
  AND capture_location ILIKE '%harbor%'
  AND capture_method ILIKE '%net%'
ORDER BY id DESC
LIMIT :perPagePlusOne;
```

- 例 A（非數字）：`last_id=abc` → 422 `{ error: "INVALID_CURSOR" }`
- 例 B（非正整數）：`last_id=0` 或 `last_id=-5` → 422 `{ error: "INVALID_CURSOR" }`
- 例 C（指向空區段）：`last_id=999999`（查無更小 id）→ 200 空集合
- 例 D（方向錯誤）：上一批最後一筆 id=500，續載卻傳 `last_id=800` → 422 `{ error: "INVALID_CURSOR" }`

具體範例：

- 範例 1：`GET /fishs?last_id=abc&perPage=20` → 422 `{ "error": "INVALID_CURSOR" }`
- 範例 2：`GET /fishs?last_id=0&perPage=20` → 422 `{ "error": "INVALID_CURSOR" }`
- 範例 3：`GET /fishs?last_id=999999&perPage=20` → 200 `{ "items": [], "pageInfo": { "hasMore": false, "nextCursor": null } }`

  錯誤回應格式（422）：

  - HTTP 狀態碼：422 Unprocessable Entity
  - JSON Body：`{ "error": "INVALID_CURSOR", "message": "Invalid or stale last_id." }`
  - `message` 為可選，用於人類可讀的提示；`error` 是機器可判斷的錯誤碼。

  | 類型       | 範例請求                                                 | 判定           | 回應                                                      | 說明                       |
  | ---------- | -------------------------------------------------------- | -------------- | --------------------------------------------------------- | -------------------------- |
  | 非整數     | `/fishs?last_id=abc&perPage=20`                          | INVALID_CURSOR | 422 `{error:INVALID_CURSOR}`                              | 無法解析為正整數           |
  | 非正整數   | `/fishs?last_id=0&perPage=20`                            | INVALID_CURSOR | 422 `{error:INVALID_CURSOR}`                              | 必須 >0                    |
  | 負數       | `/fishs?last_id=-5&perPage=20`                           | INVALID_CURSOR | 422 `{error:INVALID_CURSOR}`                              | 不合法主鍵值               |
  | 方向錯誤   | （上一批最後一筆 id=500）`/fishs?last_id=800&perPage=20` | INVALID_CURSOR | 422 `{error:INVALID_CURSOR}`                              | 違反遞減序列，可能造成重複 |
  | Stale 游標 | 改變搜尋條件仍傳舊 `last_id=500`                         | INVALID_CURSOR | 422 `{error:INVALID_CURSOR}`                              | 游標與條件不匹配須重啟     |
  | 空區段     | `/fishs?last_id=12` 而最小符合條件 id=12                 | 正常終止       | 200 `{items:[],pageInfo:{hasMore:false,nextCursor:null}}` | 尾端結束，不是錯誤         |
  | 超大合法值 | `/fishs?last_id=999999` 但無更小資料                     | 正常終止       | 200 空集合                                                | 指向合法但無資料的範圍     |

  分界原則：422 表示「參數不構成合法遞減游標語意」；200 空集合表示「游標合法，但已到尾端」。

- **FR-007**: `perPage` 範圍 1–50（上限以 `per_page_max` 規範），超界或缺失以 20（`per_page_default`）代替，後端回應中不特別標記修正（前端以本地規則還原）。

  - 註：任何不屬於正整數或不在 [1, `per_page_max`] 範圍內的輸入（含小數、字串、負數、零、超過上限），一律正規化為 `per_page_default`（預設 20）；不採用 clamp（例如 51 不改成 50，而是改為 20）。

- 補充：PWA 無限滾動情境中，`perPage` 作為伺服器批次大小，單次搜尋工作階段固定不變；若需變更（例如改為 30），前端必須自首批重新開始並丟棄舊游標。
- **FR-008**: 續載請求期間後端不應產生重複結果；游標必須對應上一批最後一筆的主鍵序；若使用者快速連續觸發，僅第一個游標序列被採用。
- **FR-009**: 回應資料需限制載重：避免 eager load 非必要關聯（部落分類、捕獲紀錄等）以達成首屏負載降低（配合 SC-004）。
- **FR-010**: 搜尋結果空集合時，不提供建議詞。
- **FR-011**: 若查詢執行時間超過門檻（1000ms），本期不要求記錄伺服器端日誌；設定值預留於 `config/fish_search.php` 的 `slow_query_ms`（可為註解/佔位），僅作為集中門檻常數。程式碼允許保留已註解的偽程式碼（例如 local 環境的 `DB::listen`/microtime 計時示範），供未來啟用時參考，不得在正式環境寫入持久日誌。
- **FR-012**: 不支援「關鍵字字典 → 結構化條件」。自由輸入僅限「名稱（name）」與「地點（capture_location）」欄位，兩者皆採 ILIKE 模糊比對；「部落（tribe）」、「食物分類（food_category）」、「處理方式（processing_method）」必須由下拉選單提供，後端不對自由文字進行維度推論或自動套用。
- **FR-013**: 首次載入（無任何搜尋條件）必須依 `per_page_default` 取得首批結果並套用標準分頁流程（lookahead + hasMore 判斷）；不得回傳超出上限（`per_page_max`）的全量或更少於 1 筆。若 `per_page_default` 設定非法（≤0 或 >`per_page_max`），正常化為 20。
- **FR-014**: 前端在發送搜尋或續載請求期間需顯示 Loading 狀態（骨架或 spinner）。後端不需額外旗標；以請求未完成期間呈現。若回應 422（INVALID_CURSOR），立即切換為錯誤 Banner（保留已載入資料）。
- **FR-015**: 安全策略：`GET /fishs` 為公開端點（不需 Sanctum 驗證，匿名允許）。回應僅包含精簡欄位（`id,name,image_url`），不得暴露使用者相關或敏感資料；若未來改為受保護，需同步更新 OpenAPI、任務與新增授權/未授權測試案例。

### Key Entities

- **FishListItem**: 列表最小資訊物件：`id`, `name`, `image_url`。不含 has_webp、音訊、備註、分類、尺寸等延伸欄位；需要進階資訊時再由詳細頁載入。
- **SearchFilters**: 前端提交的搜尋條件集合；包含 name?, tribe?, capture_location?, capture_method?, processing_method?, food_category?（皆可選）。
  - 不支援自由輸入關鍵字映射：後端不會將名稱或地點欄位中的詞彙推論為其他欄位（如 tribe/food_category/processing_method）。
- **PaginationState**: 前端狀態（cursor, hasMore, isLoading, error?, perPage）；與回應 pageInfo 對應。
- **SearchCursor**: 以明碼 `last_id`（上一批最後一筆 `id`）表達；續載查詢附加條件 `id < last_id`。取消不透明編碼，降低前端理解成本；若未來需要附加更多維度可再引入封裝結構。

  #### Cursor 說明（與畫面滾動無關）

  - 游標不是「使用者目前滾到哪裡」的 UI 位置；它是後端產生的「書籤」，記錄上一批結果的最後一筆主鍵值 `last_id`。
  - 續載請求時，前端僅將上一個回應給的 `nextCursor` 原樣帶回；後端用其中的 `last_id` 轉成條件 `WHERE id < :last_id` 並維持 `ORDER BY id DESC`。
  - 如此即使資料中途有新增或刪除（主鍵不連號），也能保證不重複、不遺漏，且查詢具良好效能（可走索引）。
  - 首批：不帶 `cursor`
    - 查詢：`SELECT ... FROM fish WHERE <filters> ORDER BY id DESC LIMIT perPage+1`
  - 建立 nextCursor：
    - 若實得 `rows > perPage`：`last_id = rows[perPage-1].id` → `nextCursor = last_id`（明碼數字）
    - 否則：`hasMore=false`、`nextCursor=null`
  - 續載：
    - 收到 `last_id=X`
    - 查詢：`SELECT ... FROM fish WHERE <filters> AND id < X ORDER BY id DESC LIMIT perPage+1`
  - 為何不用 offset？
    - offset 容易因資料插入/刪除造成重複或跳漏；而 `id < last_id` 在主鍵遞減排序下可維持穩定分頁。
  - 刪除穩定性（例五）：若上一批已回傳的區段中有多筆資料被刪除（造成 id 跳號），續載仍以原 `last_id` 作為嚴格不等式邊界；`last_id` 本身即使已不存在也不影響後續查詢，系統只會回傳更小的現存 id 集合，最終可能較預期更快達到 hasMore=false（空集合）。不會觸發 422，僅回 200 正常或 200 空集合。

**PageInfo**: 分頁資訊結構 `{ hasMore: boolean, nextCursor: number|null }`；`hasMore=true` 代表仍可續載；`nextCursor` 為上一批最後一筆的 `id`（明碼數字）。固定欄位策略：`nextCursor` 永遠存在（允許為 `null`），`hasMore=false` 時必為 `null`（不得省略欄位）。判斷規則：以前瞻抓取（`LIMIT perPage+1`）判斷是否尚有下一批；若實得筆數 `> perPage`，則 `hasMore=true` 並以第 `perPage` 筆建立 `nextCursor`；否則 `hasMore=false` 與 `nextCursor=null`。

- 例一（一般情境）：符合條件共 53 筆，`perPage=20`。第一批回 20 筆 → `hasMore=true`、`nextCursor` 指向第 20 筆；第二批回 20 筆 → `hasMore=true`；第三批回 13 筆 → `hasMore=false`、`nextCursor=null`。
- 例二（游標內容）：若上一批最後一筆 `id=1000`，則 `nextCursor=1000`。續載查詢相當於在既有條件外加 `id < 1000` 並 `order by id desc`。
- 例三（條件變更）：使用者修改任一搜尋條件後，舊 `nextCursor` 失效且不可沿用；前端應清空舊分頁狀態並以新條件自首批開始。
- 例四（資料同時新增）：若在使用者瀏覽過程有新資料插入（`id` 較大），由於續載以 `id < last_id` 取資料，新插入的較新資料不會混入後續批次；若使用者重新從首批查詢，則可見到最新資料。
- 例五（資料同時刪除）：若續載前上一批範圍內有資料被刪除導致 id 跳號，`last_id` 仍作為邊界；查詢 `id < last_id` 自然而然跳過已刪除的缺口，不重複不漏失；若無更小資料則回空集合（hasMore=false, nextCursor=null）。

## Scenario Coverage

覆蓋最重要的分頁與游標情境，並以固定欄位策略統一回應格式。

1. 首批（無 `last_id`）：回傳最多 `perPage` 筆與 `pageInfo.hasMore`、`nextCursor`；若不足 `perPage` 筆則 `hasMore=false, nextCursor=null`。
2. 續載（`last_id` 合法）且仍有下一批：回傳 `perPage` 筆並提供下一個 `nextCursor`，`hasMore=true`。
3. 續載（`last_id` 合法）但到尾端：回傳 `items=[]` 或小於 `perPage` 筆；`hasMore=false, nextCursor=null`。
4. 空結果（任一批）：`items=[]` 且 `pageInfo={ hasMore:false, nextCursor:null }`；不提供建議詞。
5. 參數缺失/非法：
   - `perPage` 非正整數或越界 → 正規化為 `per_page_default`（20），照常 200。
   - `last_id` 非正整數或方向錯誤/過時 → 422 `{ error: "INVALID_CURSOR" }`。
6. 條件變更：任何搜尋條件變更後，舊 `nextCursor` 失效；前端需清空分頁狀態並自首批開始。後端於收到不匹配的舊游標時回 422。

對應 Checklist：CHK021–CHK024。

### Measurement & Observability Plan

- 量測環境與原則：

  - 優先以後端處理時間作為主指標（不含網路延遲），在 CI/本地以 Pest 整合測試以 microtime 量測 Controller→Service→Query 的包絡時間。
  - 輔以前端 TTFB（Performance API）做人為走查；此值包含網路延遲，僅作參考，不作硬性門檻。
  - 暖機策略：每組條件先進行 3 次預熱請求再開始量測，以降低快取冷啟偏差。

- SC-001（名稱單欄位）與 SC-003（續載）：

  - 量測方法：針對固定資料集，在 Pest 測試連續執行 30 次請求，記錄每次耗時（ms），計算中位數（p50）。
  - 驗收：p50 ≤ 1000ms（SC-001），續載 p50 ≤ 800ms（SC-003）。

- SC-002（多條件≥3）與 SC-006（p95 < 1000ms）：

  - 量測方法：連續 30 次請求，計算經驗分布 p95（第 29 大值）。
  - 驗收：p95 ≤ 1800ms（SC-002）；且 95% 後端處理 < 1000ms（SC-006）。

- SC-004（首屏 payload 降幅 ≥30%）：

  - 基準定義：以改造前主分支（006-backend-search 合併前）的 `GET /fishs` 首批 20 筆回應為 Baseline，於瀏覽器開發者工具記錄 Network → Transferred/Size；同條件在本分支再次量測，比較相對降幅。
  - 若無可用基準快照：以同資料集在測試中序列化「精簡欄位（id,name,image_url）」與「完整欄位（Eloquent 預設 toArray）」的 JSON 位元組長度差，作為替代度量，需 ≥30%。

- SC-005（無重複/無遺漏）：

  - 抽樣方法：針對固定條件，自首批開始以 `nextCursor` 迭代至尾端，累計至少 200 筆資料的 id 序列。
  - 斷言：
    1. 序列嚴格遞減（每個相鄰 id 都滿足 `prev_id > next_id`）。
    2. 集合無重複（`unique(ids).length === ids.length`）。
    3. 若能取得同條件的 COUNT（測試資料庫），則 `collectedCount === countInDB`；否則以不重複且遞減作為充足證據。

- 慢查詢門檻與觀測（對應 `config/fish_search.php.slow_query_ms`）：
  - 常數值 1000（ms）作為集中來源；正式環境不記錄持久日誌。
  - 本地/測試可選：以註解示例包裝 microtime 或 `DB::listen` 進行臨時輸出到 stdout，僅供開發者觀測。

對應 Checklist：CHK018–CHK020、CHK029。

## Non-Functional Requirements

- NFR-001（Eager Load 限制）對應 FR-009：列表回應不得進行非必要關聯預載入；查詢僅選取 `id,name,image_url` 三欄，避免 N+1 與 payload 膨脹。
- NFR-002（慢查詢門檻）對應 FR-011：`slow_query_ms=1000` 為集中常數；本期不落地任何持久記錄，僅提供程式註解掛鉤與測試量測方法。
- NFR-003（公開端點的安全姿態）對應 FR-015：端點不需認證，回應欄位經白名單鎖定，避免敏感資訊外洩。

對應 Checklist：CHK028–CHK030。

## Dependencies & Assumptions

- DB 假設：
  - 主鍵 `id` 單調遞增，存在有效索引支撐 `ORDER BY id DESC` 與 `WHERE id < :cursor` 的查詢路徑。
  - 模糊欄位目前不依賴全文索引；若資料規模增大可於未來評估 trigram/GIN（本期不實作）。
- 平台限制：
  - Vercel 不提供持久 Log；因此慢查詢僅以門檻常數與本地/測試觀測為主，不在正式環境寫入持久日誌。

對應 Checklist：CHK031–CHK032。

## Success Criteria _(mandatory)_

<!--
  ACTION REQUIRED: Define measurable success criteria.
  These must be technology-agnostic and measurable.
-->

### Measurable Outcomes

- **SC-001**: 單一名稱搜尋首批回應（後端處理 + 傳輸）中位延遲 ≤ 1 秒。
- **SC-002**: 多條件複合搜尋（≥3 條件）首批回應 p95 延遲 ≤ 1.8 秒。
- **SC-003**: 續載（游標下一批）中位等待時間 ≤ 800ms。
- **SC-004**: 首屏搜尋結果回應負載（bytes）相較改造前減少 ≥ 30%。
- **SC-005**: 無重複結果或漏失（人工抽樣 200 筆游標序列驗證 0% 重疊）。
- **SC-006**: 95% 首批查詢（單一或多條件）後端處理時間 < 1000ms。
- **SC-007**: 查詢與續載期間，前端一律顯示 Loading 指示（骨架或 spinner），人工走查 10 次操作流程達成 100% 呈現。

### Assumptions

- 現有 fish 資料表主鍵為遞增 id，可支撐 `id DESC` 穩定排序。
- 關聯（部落分類、捕獲紀錄）非列表必要欄位，可延後按需載入。
- 不引入全文索引（如 PostgreSQL GIN）本期以模糊 LIKE 為主。
- 使用者多數僅使用 1–2 個搜尋條件，複合≥3 條件屬少數案例。

### Clarifications Needed (最大 3 項)

1. 查詢耗時警告門檻（毫秒）→ 1000ms（本期僅作為效能目標，不實作後端持久日誌）
2. 是否需回傳熱門搜尋建議（空結果時）？→ 不回；維持極簡 API（已澄清）
3. 名稱搜尋大小寫不敏感：已確認採 ILIKE 實作（使用者回覆：透過英文字母做搜尋，沒有大小寫的分別）。

## Clarifications

### Session 2025-11-07

- Q: 游標是否等同使用者滾動位置？與 fish id 何關？ → A: 游標是後端產生的「書籤」，包含上一批最後一筆的 `id`（`last_id`），非 UI 位置；續載以 `WHERE id < last_id ORDER BY id DESC` 取得下一批，確保不重複/不遺漏。

- Q: 名稱搜尋是否大小寫不敏感？ → A: 採 ILIKE，英文字母搜尋不區分大小寫。
- Q: `pageInfo.hasMore`、`nextCursor` 是什麼意思？ → A: `hasMore` 表示是否尚有下一批資料；`nextCursor` 為上一批最後一筆的主鍵 id（明碼數字）。固定欄位策略：`hasMore=false` 時 `nextCursor=null`（不省略欄位）。
- Q: `hasMore` 的判斷與使用情境為何？ → A: 以 `perPage+1` 前瞻抓取決定；>perPage 視為尚有下一批並回 `nextCursor`，否則 `hasMore=false`。條件變更時舊游標失效，並保證依 `id DESC` 穩定排序避免重疊或遺漏。
- Q: PWA 無限滾動下 perPage 如何運作？ → A: 固定每批 20 筆，單次搜尋階段不得動態改變；改變需重啟首批並捨棄舊游標。
- Q: 是否需要「搜尋狀態可分享與返回還原」？ → A: 本期不需要，已刪除 User Story 3 與相關情境/驗收案例。
- Q: 列表 item 欄位範圍？ → A: 僅回傳 `id`, `name`, `image_url` 三欄，符合 FR-002 精簡策略。
- Q: 部落、捕獲地點與處理方式是否大小寫不敏感？ → A: 是，部落採等值比對但大小寫不敏感；捕獲地點與處理方式採模糊 ILIKE，同樣大小寫不敏感。
- Q: FR-006 的錯誤情境有哪些？ → A: `last_id` 非正整數或方向錯誤 422；指向空區段則回 200 空集合；不回退全量。
- Q: 慢查詢門檻（slow_query_ms）是多少？ → A: 1000ms。設定值可放 `config/fish_search.php` 但本期不啟用伺服器端持久日誌，僅作為效能目標；程式碼可保留註解掛鉤以利未來啟用。
- Q: 空結果是否提供熱門建議？ → A: 本期不提供。當查無資料時回傳 `items=[]` 與 `pageInfo.hasMore=false`，不額外觸發熱門/猜你想找查詢，以降低額外負載並維持 API 行為可預期性。

### Session 2025-11-08

- Q: 422 是否為 HTTP 狀態碼？`INVALID_CURSOR` 與其關係？ → A: 是，採用 `422 Unprocessable Entity`；錯誤格式 `{ error: "INVALID_CURSOR", message?: "Invalid or stale last_id." }`（`message` 可選）。
- Q: 「非阻斷錯誤橫幅」是什麼？ → A: 顯示於列表或頁面可見區域的提示 Banner，不遮蔽內容，使用者仍可操作；通常含行動按鈕（如「重新開始」）。
- Q: 「阻斷式對話框」是什麼？ → A: Modal 對話框，彈出後背景不可操作，需明確關閉/確認才可繼續；僅用於需強制注意的情境，搜尋續載錯誤不採用。
- Q: 422 錯誤觸發時採用哪種 UX？ → A: 採非阻斷錯誤橫幅（底部 Banner）與「重新開始」按鈕；點擊後清空 last_id 重載首批，保留既有搜尋條件。
- Q: 查詢耗時門檻與 UX 呈現？ → A: 目標 ≤1000ms；超過 1000ms 仍正常回應（本期不記錄伺服器端 warning）。查詢與續載等待期間，前端顯示 Loading（骨架或 spinner）避免空白落差。
- Q: Vercel 無法持久記錄 Log，慢查詢如何處理？ → A: 本期僅追求效能門檻（SC-006），不實作記錄；保留程式碼註解計時掛鉤，未來若遷移平台或導入 APM 再啟用記錄與分析。
- Q: 初次列表載入筆數如何決定？ → A: 依 `per_page_default`（預設 20）進行首批查詢，套用 lookahead 分頁判斷，避免全量載入造成首屏延遲。

- Q: 自由輸入欄位與下拉選單的邊界是？ → A: 只有「地點、名稱」是文字輸入；「部落、食物分類、處理方式」皆為下拉選單。後端不進行自由文字到結構化條件（如 tribe/food_category）的關鍵字映射。對應調整：刪除關鍵字字典解析（更新 FR-012）、移除關鍵字衝突案例，並將驗收案例 #5 改為僅做名稱模糊比對。
- Q: 空結果是否要回熱門搜尋建議？ → A: 不回；保持簡單可預期回應（`items=[]`, `pageInfo.hasMore=false`），避免額外查詢負擔與 UI 分岔，本期不實作推薦模組。
- Q: 地點（capture_location）是否也與名稱一樣大小寫不敏感？ → A: 是，採 ILIKE；與名稱相同的模糊比對邏輯，不區分大小寫。
- Q: API 是否需版本化（/v1）？ → A: 不需要；本期僅一條搜尋 API，維持 `/fishs`，未來破壞性變更再引入版本。
- Q: 設定 `slow_query_ms=1000` 但本期不記錄日誌，實際作用是？ → A: 目前僅作為集中門檻常數（單一真實來源）。觀測方式：
  1.  本地/測試環境可臨時啟用 `DB::listen`/計時包裝判斷是否超過門檻並輸出 console；
  2.  Pest 測試可對特定複合條件測量執行時間（microtime 差值）並斷言 `< slow_query_ms`；
  3.  前端可記錄 TTFB（Performance API）與後端門檻對照（僅供參考，含網路延遲）；
  4.  未來若導入 APM 或平台遷移，只需接上此設定值而不需搜尋散落常數；
  5.  若暫時需要人工分析，可加一段註解式計時程式碼（例如 `if(app()->environment('local'))`）輸出到 stdout，不進行持久儲存。
- Q: 是否要在 config 預留 slow_query_ms 並於程式碼提供註解示例？ → A: 是（Option C）。於 `config/fish_search.php` 預留 `slow_query_ms: 1000`（含註解），並在 Service 以註解示例 microtime/DB::listen 的用法；正式環境不寫入持久日誌。

#### Decision: Cursor Encoding (supersedes older research)

- 決策：游標改用「明碼數字 last_id」（上一批最後一筆主鍵 id），不再使用不透明編碼（例如 base64 封裝）。
- 理由：
  - 降低前端理解與除錯成本，直接以數字 `nextCursor` 帶回更直觀。
  - 搭配 `id DESC` 與 `WHERE id < last_id` 可保證穩定分頁、不重複不遺漏，並可走索引，效能充足。
  - 未包含敏感資訊，暴露主鍵 `id` 在列表情境可接受。
- 影響：
  - 規格中已以明碼 `last_id` 定義 `PageInfo.nextCursor`（見 FR-005、Key Entities: SearchCursor）。
  - 若未來需要加入額外維度（例如 created_at 或 shard key），可以在保持向後相容的前提下，改以封裝結構或不透明字串；前端只需「原樣傳回」即可。
  - 舊研究（feature 005）提到的 opaque base64 游標視為過時，僅供歷史參考，不影響本功能實作。

### Session 2025-11-09

- Q: `GET /fishs` 是否為公開端點或需 Sanctum 授權？ → A: 公開端點，任何人可存取（匿名允許），不進行身份驗證；回應不含敏感資訊。
- Q: API 參數 `capture_location` 與資料表欄位 `location` 是否需同名？ → A: 不必；對外維持語義清楚的 `capture_location`，內部在查詢層映射到 DB 欄位 `location` 以保留解耦與未來調整彈性。
- Q: OpenAPI 版本是否升級到 3.1.0？ → A: 是，採 3.1.0 以符合 lint 偏好並獲得 JSON Schema 2020-12 對齊；`openapi.yaml` 已更新並使用 `jsonSchemaDialect` 與 oneOf 取代舊版 nullable。

- Q: 是否需要在 FR-006 中區分 INVALID_CURSOR 與空區段案例的表格？ → A: 已加入，透過類型/範例請求/回應碼清楚對照，422 僅用於語意不合法，合法但無資料則 200 空集合。

- Q: `perPage` 越界策略需要 clamp 還是回預設？ → A: 不採 clamp；凡非正整數或不在 [1, `per_page_max`] 之內者，一律正規化為 `per_page_default`（20）。此為降低資源放大與維持契約/測試一致性的明確規範。

- Q: 刪除造成 id 跳號是否影響游標正確性？ → A: 不影響；`WHERE id < last_id` 不依賴連號，last_id 即使被刪除仍提供正確嚴格邊界，只會讓剩餘資料更少，最終正常回 200 空集合，不觸發 422。
- Q: `tribe` 是否允許模糊或部分比對？ → A: 不允許。部落比對僅為「大小寫不敏感之等值」，採 `LOWER(tribe_column)=LOWER(:tribe)`；使用者輸入若含 `%` 或 `_` 字元視為普通文字，不展開為通配。此行為旨在避免對非模糊欄位產生計畫膨脹與不可預期結果，與 CHK034 要求一致。
- Q: 參數或設定中的批次大小鍵名是否有多種拼寫（`max_per_page` vs `per_page_max`）？ → A: 已統一採用 `per_page_default` 與 `per_page_max` 兩個名稱；規格與後續 config 使用相同命名，不再使用 `max_per_page`、`page_size_default` 等變體（支援 CHK033）。
- Q: 新增路由應放在 `routes/api.php` 還是 `routes/web.php`？ → A: 依專案現行慣例，新增路由一律放在 `routes/web.php`，不再在 `routes/api.php` 增加新條目；前端採 Inertia.js 呼叫並接收 JSON。
- Q: 搜尋文字參數的空白/空字串處理規則？ → A: 先 trim；若結果為空字串則忽略該條件，不產生 ILIKE '%%'（避免全域匹配與非必要查詢負載）。
- Q: `capture_method` 的比對規則為何？ → A: 採模糊 ILIKE（大小寫不敏感），先 trim；若為空字串則忽略，不產生 ILIKE '%%'。
- Q: 精簡回應欄位是否鎖定白名單並限制隨意擴張？ → A: 是，僅 `id,name,image_url`；新增欄位必須經規格更新與測試審核，未來若需擴充可透過版本化或新增 `fields` 參數（本期不實作）。
