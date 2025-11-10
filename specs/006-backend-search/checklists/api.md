# API Requirements Quality Checklist — Backend Server-Side Search (006-backend-search)

Purpose: Unit tests for English — validate the quality of written API requirements, not the implementation.
Created: 2025-11-09
Focus: API contracts, error semantics, pagination, filters, payload minimization, security posture
Depth: Standard
Audience: Author (Self-check)

## Requirement Completeness

- [x] CHK001 — 是否為 `GET /fishs` 定義了所有可用查詢參數（`name`, `tribe`, `capture_location`, `capture_method`, `processing_method`, `food_category`, `perPage`, `last_id`）與其型別/範圍？[Completeness, Spec §FR-001]
- [x] CHK002 — 是否定義回應結構完整且唯一來源（`items[]`, `pageInfo{ hasMore, nextCursor }`）與欄位型別？[Completeness, Spec §FR-002]
- [x] CHK003 — 是否提供 200 / 422 情境的完整回應範例（含空集合案例）？[Completeness, Spec §FR-006, FR-010]
- [x] CHK004 — 是否於 `config/fish_search.php` 明確記錄 `per_page_default` 與 `per_page_max` 並於規格中被引用？[Completeness, Spec §FR-007, FR-013]
- [x] CHK005 — 是否明示所有支援的比對規則（ILIKE 模糊、等值但大小寫不敏感等）對應到各欄位？[Completeness, Spec §FR-003]
- [x] CHK006 — 是否定義查無資料時之回應與 UI 不需建議詞的要求？[Completeness, Spec §FR-010]
- [x] CHK007 — 是否明確列出不支援的行為（關鍵字字典解析、offset 分頁等）？[Completeness, Spec §FR-012, PageInfo section]

## Requirement Clarity

- [x] CHK008 — `perPage` 正規化規則是否具體且可量化（範圍 1–50，越界/缺漏採 20）？[Clarity, Spec §FR-007]
- [x] CHK009 — 422 錯誤之「非法游標」判定是否以範例界定（非整數、<=0、方向錯誤與空區段差異）？[Clarity, Spec §FR-006]
- [x] CHK010 — `nextCursor` 的定義是否明確為上一批最後一筆 id（明碼數字），且何時為 null？[Clarity, Spec §FR-005, PageInfo]
- [x] CHK011 — 「大小寫不敏感」的實作語意是否以 ILIKE/LOWER 規律清楚落點到每個欄位？[Clarity, Spec §FR-003]
- [x] CHK012 — 「非阻斷錯誤橫幅」與「阻斷式對話框」的差異是否以定義敘述清楚？[Clarity, Spec §FR-014, Clarifications 2025-11-08]
- [x] CHK013 — 「僅回傳精簡欄位」的限制是否列有白名單並避免歧義？[Clarity, Spec §FR-002]

## Requirement Consistency

- [x] CHK014 — 游標編碼決策（明碼 last_id）是否與 PageInfo/範例/錯誤處理一致？[Consistency, Cursor Decision, Spec §FR-005]
- [x] CHK015 — 公開端點安全姿態（不需 Sanctum）是否與回應欄位精簡策略一致而不洩漏敏感資訊？[Consistency, Spec §FR-015, FR-002]
- [x] CHK016 — 首批查詢使用 `per_page_default` 的規定是否與 `perPage` 正規化一致（缺省即採預設 20）？[Consistency, Spec §FR-007, FR-013]
- [x] CHK017 — AND 組合邏輯與空值忽略是否與所有範例與使用者故事敘述一致？[Consistency, Spec §FR-004, US2]

## Acceptance Criteria Quality（Measurability）

- [x] CHK018 — 是否為效能目標提供可量測的門檻（中位/95 百分位/中位載入時間）且與觀測方法相容？[Acceptance Criteria, Spec §SC-001..SC-003, SC-006, Measurement & Observability]
- [x] CHK019 — 首屏 payload 降幅（≥30%）是否有明確基準與度量方式來源？[Acceptance Criteria, Spec §SC-004, Measurement & Observability]
- [x] CHK020 — 「無重複/無遺漏」是否定義樣本量與驗證方法（抽樣 200 筆游標序列）？[Acceptance Criteria, Spec §SC-005, Measurement & Observability]

## Scenario Coverage

- [x] CHK021 — 是否涵蓋首批、續載（hasMore=true/false）的所有情境與相對應的 `nextCursor` 規則？[Coverage, PageInfo, Scenario Coverage]
- [x] CHK022 — 是否涵蓋空結果（items=[]）的語意與 pageInfo 呈現（hasMore=false, nextCursor=null）？[Coverage, Spec §FR-006, FR-010, Scenario Coverage]
- [x] CHK023 — 是否涵蓋參數缺失/非法（perPage 越界、last_id 非法）的處理分流（正規化 vs. 422）？[Coverage, Spec §FR-006, FR-007, Scenario Coverage]
- [x] CHK024 — 是否涵蓋條件變更後舊游標失效須重啟首批的要求？[Coverage, PageInfo Examples, Scenario Coverage]

## Edge Case Coverage

- [x] CHK025 — 是否定義「方向錯誤游標」（last_id 大於或等於上一批最後一筆）的明確判斷與回應？[Edge Case, Spec §FR-006]
- [x] CHK026 — 是否說明「指向空區段」時的處理（200 空集合）與 422 的差異？[Edge Case, Spec §FR-006]
- [x] CHK027 — 是否定義重複觸發續載或競態情形下的採用規則（僅第一序列有效）？[Edge Case, Spec §FR-008]

## Non-Functional Requirements

- [x] CHK028 — 是否明確限制回應不進行不必要的 eager load 以降低成本？[Non-Functional, Spec §FR-009, SC-004, Non-Functional]
- [x] CHK029 — 是否記錄慢查詢門檻 `slow_query_ms=1000` 的定位（僅常數，無持久記錄）與未來可擴展性？[Non-Functional, Spec §FR-011, Measurement & Observability]
- [x] CHK030 — 是否明確記載端點公開且無認證需求，並避免回應含敏感資訊？[Non-Functional, Spec §FR-015, Non-Functional]

## Dependencies & Assumptions

- [x] CHK031 — 是否列出資料庫假設（id 遞增、索引可支撐 id DESC）與對查詢策略的影響？[Assumptions, Spec §Dependencies & Assumptions]
- [x] CHK032 — 是否記載平台限制（Vercel 無持久 Log）與相對應策略（僅占位設定/註解鉤子）？[Dependency, Spec §Dependencies & Assumptions]

## Ambiguities & Conflicts

- [x] CHK033 — 命名已統一為 `per_page_default` 與 `per_page_max`（不使用 max_per_page 等變體），並與規格敘述一致。[Ambiguity, Spec §FR-007, Tasks T008a]
- [x] CHK034 — `tribe` 比對已明確為「大小寫不敏感之等值」（LOWER(column)=LOWER(:tribe)；不使用模糊 ILIKE），且規格已禁止通配符造成模糊語意。[Ambiguity, Spec §FR-003]
- [x] CHK035 — 採固定欄位策略：`nextCursor` 永遠存在（可為 null），`hasMore=false` 時必為 null，不省略；規格與契約一致。[Ambiguity, PageInfo]

## Traceability & ID Scheme

- [x] CHK036 — 是否於 OpenAPI（contracts/openapi.yaml）中引用 FR/SC 編號或以註解標示來源段落以利交叉追蹤？[Traceability, Spec §FR-* §SC-*]
- [x] CHK037 — 是否於程式碼註解標示關鍵規格對應（例如 Service/Controller 註解 FR-003, SC-006）？[Traceability, Tasks T042]

--

Total Items: 36
