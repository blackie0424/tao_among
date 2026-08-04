# Feature Specification: 耆老問答系統 (Elder Chat)

**Feature Branch**: `feature/elder-chat`
**Created**: 2026-06-18
**Status**: Confirmed

## 概述

將魚類圖鑑的所有資料（筆記、部落知識、捕撈紀錄、部落分類），化身為一位**在地耆老**，讓使用者以自然語言提問，耆老根據資料庫中的真實知識回答。

技術架構：**RAG（關鍵字檢索）+ Claude API + Vue 3 Chat UI**

---

## User Scenarios & Testing

### User Story 1 - 詢問特定魚種知識 (Priority: P1)

使用者輸入魚的名字，耆老依據資料庫中該魚的筆記、部落分類、參考知識、捕撈紀錄，給出回答。

**Why this priority**: 核心使用情境，直接驗證整個 RAG + Claude 流程。

**Independent Test**: 在資料庫有資料的魚（如「苦花」），問「苦花怎麼抓？」，耆老應能引用捕撈紀錄或部落知識回答。

**Acceptance Scenarios**:

1. **Given** 資料庫有「苦花」的捕撈記錄，**When** 使用者輸入「苦花怎麼捕撈？」，**Then** 耆老回答中應包含實際的捕撈方法或地點資訊，且口吻溫和帶部落語感。
2. **Given** 資料庫無「XX魚」任何資料，**When** 使用者輸入「XX魚怎麼做？」，**Then** 耆老應坦誠說「這條魚我沒有相關的記載」，不憑空捏造。
3. **Given** 使用者提問，**When** Claude API 呼叫失敗，**Then** 回傳 HTTP 500 並給使用者友善錯誤訊息。

---

### User Story 2 - 多輪對話 (Priority: P2)

使用者可以追問，耆老能記住這輪對話脈絡。

**Why this priority**: 讓互動更自然，但 P1 的單輪回答已有價值。

**Independent Test**: 先問「苦花在哪裡抓？」，再追問「那個季節最多？」，耆老應知道「那個」指苦花。

**Acceptance Scenarios**:

1. **Given** 使用者已問過「苦花」，**When** 追問「牠的部落名稱是什麼？」，**Then** 耆老應理解問的是苦花並正確回應。
2. **Given** 對話超過 10 輪，**When** 繼續提問，**Then** 系統只保留最近 6 輪 history，避免 token 爆炸。

---

### User Story 3 - 來源透明度 (Priority: P3)

回答下方顯示本次回答引用了哪些魚的資料（不顯示原文，只顯示魚名 + 資料類型）。

**Why this priority**: 增加信任感，讓使用者知道耆老的話有所本。

**Acceptance Scenarios**:

1. **Given** 耆老回答引用了「苦花」的捕撈紀錄，**Then** 回應 `sources` 應包含 `{ fish: "苦花", types: ["capture_record", "tribal_classification"] }`。

---

### Edge Cases

- 使用者輸入超長文字（>2000 字）：後端 validate 並回傳 422。
- 使用者輸入 prompt injection（如「忽略以上指令」）：system prompt 明確說明角色與邊界，Claude 自然應對。
- 資料庫完全沒有魚的資料：耆老回「我的記憶中還沒有這方面的知識，但你可以把這段知識告訴我」。

---

## Requirements

### Functional Requirements

- **FR-001**: 系統 MUST 提供 `POST /api/elder/chat` API endpoint。
- **FR-002**: `ElderContextService` MUST 根據問題關鍵字，從 `fish`、`fish_notes`、`reference_knowledge`、`capture_records`、`tribal_classifications` 查詢相關資料。
- **FR-003**: 系統 MUST 呼叫 Anthropic Claude API（`claude-sonnet-4-6`），以耆老 persona 生成回答。
- **FR-004**: 系統 MUST 使用 `ANTHROPIC_API_KEY` 環境變數，不得 hardcode。
- **FR-005**: API 回應 MUST 包含 `answer`（string）與 `sources`（array）欄位。
- **FR-006**: 前端 MUST 提供 `/elder` Inertia 頁面，包含聊天介面。
- **FR-007**: 聊天介面 MUST 在送出後顯示 loading 狀態，API 回應後顯示耆老回答。
- **FR-008**: `question` 欄位 MUST validate：非空、最長 2000 字元。
- **FR-009**: `history` 欄位最多保留最近 **6 輪**對話（12 則 messages）。

### Context Building 策略

`ElderContextService::buildContext(string $question, ?int $fishId = null, ?string $tribe = null): array`

```
回傳：
{
  context: string,   // 格式化給 Claude 的知識文字
  sources: array     // 引用了哪些資料 [{ fish_id, fish_name, types[] }]
}
```

**搜尋邏輯（三層優先級）**：

1. **有 `fish_id`** → 直接載入該魚的完整資料（notes、tribal_classifications、reference_knowledge、capture_records），`tribe` 有值時 tribal_classifications 只取該部落。
2. **無 `fish_id` 但有 `tribe`** → keyword pre-filter（`Fish.name` LIKE 問題字串），限定 `tribal_classifications.tribe = tribe`，最多 **20 條魚**。
3. **兩者皆無** → keyword pre-filter 全庫（`Fish.name` + `fish_notes.note` + `reference_knowledge.content` LIKE 問題前 50 字），最多 **20 條魚**。

**Context 格式（傳給 Claude 的 user context block）**：

```
以下是知識庫中與你問題相關的記載：

【魚名：苦花】
- 部落分類（泰雅族）：食物類別 = 主食魚類；處理方式 = 曬乾、醃漬
- 部落知識（來源：泰雅族漁獵知識彙編，第 23 頁）：苦花喜冷水，秋冬溯溪上游...
- 捕撈紀錄（2024-11-03，泰雅族，秀巒溪）：使用魚笱，捕獲量豐
- 筆記：生活在海拔 800m 以上清澈溪流
```

### System Prompt（耆老 Persona）

```
你是一位熟悉台灣原住民族漁獵文化的在地耆老，人稱「阿公」。
你一生在溪邊與海邊生活，熟知各種魚類的習性、族語名稱、捕捉方式與料理方法。

回答規則：
1. 只根據【知識庫】中提供的資料回答，不要自行虛構細節。
2. 若知識庫中找不到相關資料，誠實說「這條魚，我的記憶中沒有相關記載」。
3. 語氣溫和、帶有長者的從容，可以使用「阿孫啊」等親切稱謂。
4. 回答用正體中文。
5. 不要透露你是 AI 或 Claude，你就是耆老。
```

### Key Entities

- **ElderChatController**: 處理 HTTP 請求、validate、串接服務
- **ElderContextService**: 資料庫查詢 + context 格式化（`buildContext()`）
- **ElderChatService**: 呼叫 Claude API（`chat()`），管理 system prompt
- **ElderChat.vue**: Vue 3 聊天頁面（Inertia page）
- **ElderChatMessage.vue**: 單則對話泡泡元件

---

## API 規格

### `POST /api/elder/chat`

**Request Body**:
```json
{
  "question": "苦花怎麼捕撈？",
  "fish_id": 12,
  "tribe": "iraraley",
  "history": [
    { "role": "user", "content": "上一個問題" },
    { "role": "assistant", "content": "上一個回答" }
  ]
}
```

**Response 200**:
```json
{
  "answer": "阿孫啊，苦花喜歡在清澈的冷水溪流...",
  "sources": [
    { "fish_id": 3, "fish_name": "苦花", "types": ["capture_record", "tribal_classification"] }
  ]
}
```

**Response 422**:
```json
{ "message": "The question field is required." }
```

---

## 檔案結構

```
app/
  Http/Controllers/ElderChatController.php
  Services/
    ElderContextService.php
    ElderChatService.php
routes/
  api.php  (新增路由)
  web.php  (新增 /elder 頁面路由)
resources/js/
  Pages/ElderChat.vue
  Components/ElderChatMessage.vue
```

---

## 環境變數

```env
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-sonnet-4-6
```

加入 `.env.example`。

---

## Success Criteria

- **SC-001**: 對有資料的魚提問，耆老能在 10 秒內回答，且回答中包含資料庫中的具體資訊（地名、方法等）。
- **SC-002**: 對無資料的魚提問，耆老不憑空捏造，明確說明沒有相關記載。
- **SC-003**: Unit test 覆蓋 `ElderContextService::buildContext()` 的三種情境：魚名精確命中、關鍵字模糊命中、無命中。
- **SC-004**: Feature test 覆蓋 `POST /api/elder/chat` 的正常流程與 422 validation error。

---

## 實作備註

- 呼叫 Claude API 使用 **Laravel HTTP Client**（`Http::withHeaders()`），不需要額外 PHP SDK。
- Model 使用 `claude-sonnet-4-6`。
- 不需要 vector DB，關鍵字搜尋對此資料量已足夠。
- 若未來資料量增大，可在 `ElderContextService` 替換為 vector search，介面不變。
