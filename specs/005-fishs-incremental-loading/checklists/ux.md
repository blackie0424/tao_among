# 需求品質檢查清單（UX/API）：魚類清單分批載入

目的：用作「需求的單元測試」，驗證規格是否清楚、完整、可驗收（非實作/程式層面）  
建立日期：2025-11-04  
對應規格：../spec.md

## Requirement Completeness（完整性）

- [ ] CHK001 是否完整定義首屏/續載/失敗/空狀態/終點等狀態的需求？[Completeness, Spec §User Scenarios, §Edge Cases]
- [ ] CHK002 分頁合約是否完整（請求 cursor/perPage、回應 items/pageInfo.hasMore/nextCursor）？[Completeness, Spec §FR-001]
- [ ] CHK003 搜尋可用性何時啟用的規則是否完整（hasMore=false 才啟用）？[Completeness, Spec §FR-007]
- [ ] CHK004 是否明確列出本里程碑不調整單筆 item 欄位（維持現行）？[Completeness, Spec §FR-001 備註, §Key Entities]
- [ ] CHK005 延後的圖片雙格式（WebP/JPEG）是否已清楚標記為 Deferred？[Completeness, Spec §FR-010 Deferred]

## Requirement Clarity（清晰度）

- [ ] CHK006 `cursor` 與 `nextCursor` 的語義是否明確（首批可為 null/未傳、opaque）？[Clarity, Spec §FR-001]
- [ ] CHK007 `hasMore` 的定義與啟用搜尋的關係是否明確（hasMore=false 才啟用）？[Clarity, Spec §FR-001, §FR-007]
- [ ] CHK008 首屏 1 秒內可見的衡量方式是否被描述（FCP/代理量測）？[Clarity, Spec §Success Criteria SC-001]
- [ ] CHK009 「一次性提示」的觸發條件與時機是否明確（由 hasMore→false 時觸發）？[Clarity, Spec §FR-007]
- [ ] CHK010 續載失敗時的錯誤呈現與重試行為是否清楚描述？[Clarity, Spec §FR-004, §User Story 2]

## Requirement Consistency（一致性）

- [ ] CHK011 User Stories 與 FR-007 的搜尋啟用規則是否一致？[Consistency, Spec §User Story 2, §FR-007]
- [ ] CHK012 Key Entities（FishListItem 不改、PaginationState 使用 hasMore/游標）是否與 FR-001 對齊？[Consistency, Spec §Key Entities, §FR-001]
- [ ] CHK013 Clarifications 決策是否已反映到對應 FR/章節（無互相矛盾）？[Consistency, Spec §Clarifications, §FR-001/§FR-007/§FR-010]

## Acceptance Criteria Quality（可驗收性）

- [ ] CHK014 續載新增資料的等待時間目標（≤ 800ms 中位數）是否可量測？[Acceptance, Spec §SC-002]
- [ ] CHK015 CLS ≤ 0.1 是否有對應設計（占位/固定比例）以支撐驗收？[Acceptance, Spec §SC-003]
- [ ] CHK016 失敗重試一鍵完成且不回退的要求是否可驗收？[Acceptance, Spec §SC-004, §FR-004]
- [ ] CHK017 首屏回應負載下降 ≥ 30% 的衡量方式是否明確（基準/比較方法）？[Acceptance, Spec §SC-005]

## Scenario Coverage（情境覆蓋）

- [ ] CHK018 是否涵蓋主要流程：首屏、續載、載完後啟用搜尋？[Coverage, Spec §User Stories]
- [ ] CHK019 是否涵蓋替代流程：續載失敗與重試；重覆觸發的節流/上鎖？[Coverage, Spec §FR-004, §FR-008]
- [ ] CHK020 是否涵蓋例外/恢復：離線/慢網的行為與提示？[Coverage, Spec §Edge Cases]

## Edge Case Coverage（邊界情境）

- [ ] CHK021 無資料、清單終點、慢網/離線、重覆觸發等邊界是否都有明確描述？[Edge Case, Spec §Edge Cases]
- [ ] CHK022 搜尋在未載完時的使用者引導與文案是否定義？[Edge Case, Spec §FR-007]

## Non-Functional Requirements（非功能性）

- [ ] CHK023 效能目標是否覆蓋關鍵指標（FCP/首屏、續載延遲、CLS）並可量測？[NFR, Spec §Success Criteria]
- [ ] CHK024 可觀測性（記錄/指標）是否有要求或明確說明不在範圍？[Gap]
- [ ] CHK025 可近用性（a11y）：停用圖示的可達性/可讀提示是否有要求或標註待定？[Gap]

## Dependencies & Assumptions（相依與假設）

- [ ] CHK026 是否明確假設 perPage 的預設與調整範圍？[Assumption, Spec §FR-001, §Assumptions]
- [ ] CHK027 是否標註本里程碑只引入分頁包裝，不改資料形狀（避免跨層衝擊）？[Dependency, Spec §FR-001 備註]

## Ambiguities & Conflicts（含糊與衝突）

- [ ] CHK028 「最小必要欄位」vs.「不改欄位」是否無矛盾（以後者為準）？[Conflict, Spec §FR-001, §Key Entities]
- [ ] CHK029 FR-010 Deferred 是否與任何先前敘述衝突（已移除舊敘述）？[Conflict, Spec §FR-010]
- [ ] CHK030 是否需要額外術語定義（如「首批」、「一次性提示」）或已在文中足夠清楚？[Ambiguity]

說明：

- 本清單僅檢查「需求品質」，不檢查實作或程式行為。
- 盡量讓每一項都能從規格中找到依據或明確標註為缺口（[Gap]/[Conflict]/[Ambiguity]）。
