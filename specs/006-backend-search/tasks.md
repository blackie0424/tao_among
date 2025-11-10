# Tasks: Backend Server-Side Search (006-backend-search)

## Phase 1: Setup

- [x] T001 Create config entry (commented slow_query_ms) in config/fish_search.php
- [x] T002 Confirm existing route GET /fishs in routes/web.php (no new route in api.php; add TODO comment if needed)
- [x] T003 Prepare existing controller method app/Http/Controllers/FishController.php@getFishs for backend search integration (no new controller)
- [x] T004 Create request class skeleton app/Http/Requests/FishSearchRequest.php (extends FormRequest) with authorize() returning true
- [x] T005 Create service skeleton app/Services/FishSearchService.php with search(filters, perPage, lastId) method stub
- [x] T006 [P] Ensure model app/Models/fish.php has needed scopes or note TODO for search (no changes yet)
- [x] T007 Add spec reference docblock headers to new PHP files for traceability

## Phase 2: Foundational

- [x] T008 Implement validation rules in FishSearchRequest (perPage range, last_id positive integer, filters sanitization) app/Http/Requests/FishSearchRequest.php
- [x] T008a Define perPage config keys per_page_default=20, per_page_max=50 in config/fish_search.php
- [x] T009 Implement FishSearchService query builder (apply filters, id DESC, lookahead limit) app/Services/FishSearchService.php
- [x] T010 [P] Implement pagination logic (lookahead perPage+1, derive hasMore/nextCursor) in service app/Services/FishSearchService.php
- [x] T011 Implement controller getFishs() binding Request→Service→return slim data envelope via Inertia props app/Http/Controllers/FishController.php
- [x] T012 Reuse existing web route GET /fishs (routes/web.php); ensure no changes in routes/api.php
- [x] T013 Add OpenAPI contract draft specs/006-backend-search/contracts/openapi.yaml (endpoint, params, responses 200/422)
- [x] T014 [P] Add data-model.md with entities (FishListItem, SearchFilters, PageInfo) specs/006-backend-search/data-model.md
- [x] T015 [P] Add quickstart.md (curl examples, Loading UX notes, 422 banner) specs/006-backend-search/quickstart.md

## Phase 3: User Story 1 (P1) - 單欄位快速搜尋

> Tests FIRST：先撰寫並執行失敗測試，再開始實作。

- [x] T018a [US1] Feature test: perPage normalization (out-of-range→20, initial load count) tests/Feature/FishSearch/PerPageNormalizationTest.php
- [x] T018 [US1] Add Feature test: initial load + name search tests/Feature/FishSearch/IndexPaginationTest.php
- [x] T019 [US1] Add Feature test: invalid cursor 422 tests/Feature/FishSearch/InvalidCursorTest.php
- [x] T020 [US1] Add Feature test: empty result tests/Feature/FishSearch/EmptyResultTest.php
- [x] T021 [US1] Add Unit test: pagination logic (lookahead) tests/Unit/Services/FishSearchServiceTest.php
- [x] T021a [US1] Unit test: perPage normalization boundaries tests/Unit/Requests/FishSearchRequestTest.php
- [x] T026 [US1] Frontend test: loading & banner render resources/js/Tests/FishList.spec.ts
- [ ] T016 [US1] Implement name ILIKE filter in service app/Services/FishSearchService.php
- [ ] T017 [US1] Return slim fields (id,name,image_url) only in controller transform app/Http/Controllers/FishController.php
- [x] T022 [US1] Frontend: create loading indicator component resources/js/Components/Global/FishSearchLoading.vue
- [x] T023 [US1] Frontend: implement search form + request + state in resources/js/Pages/Fishs.vue (or Search.vue if canonical)
- [x] T024 [US1] Frontend: implement infinite scroll trigger (IntersectionObserver) resources/js/Pages/Fishs.vue
- [x] T025 [US1] Frontend: implement 422 bottom banner component resources/js/Components/Fish/FishSearchCursorErrorBanner.vue

## Phase 4: User Story 2 (P2) - 多條件複合搜尋

> Tests FIRST：先撰寫並執行失敗測試，再開始實作。

- [x] T034 [US2] Feature test: multi-condition AND combination tests/Feature/FishSearch/MultiConditionTest.php
- [x] T035 [US2] Unit test: each filter individually + combined tests/Unit/Services/FishSearchServiceTest.php
- [x] T027 [US2] Implement tribe exact case-insensitive equality (LOWER=LOWER) filter in service app/Services/FishSearchService.php
- [x] T028 [US2] Implement capture_location ILIKE filter in service app/Services/FishSearchService.php
- [x] T029 [US2] Implement capture_method optional ILIKE filter app/Services/FishSearchService.php
- [x] T030 [US2] Implement processing_method ILIKE filter app/Services/FishSearchService.php
- [x] T031 [US2] Implement food_category filter app/Services/FishSearchService.php
- [ ] T032 [US2] Frontend: add dropdowns (tribe, food_category, processing_method, capture_method, capture_location text) resources/js/Pages/Fishs.vue
- [ ] T033 [US2] Frontend: preserve filters when paginating resources/js/Pages/Fishs.vue
- [x] T032 [US2] Frontend: add dropdowns (tribe, food_category, processing_method, capture_method, capture_location text) resources/js/Pages/Fishs.vue
- [x] T033 [US2] Frontend: preserve filters when paginating resources/js/Pages/Fishs.vue

## Phase 5: Polish & Cross-Cutting

- [ ] T036 Add performance measurement helper (commented local timing) app/Services/FishSearchService.php
- [ ] T037 Add README section linking spec & quickstart README.md
- [ ] T038 [P] Add OpenAPI examples for error/empty response specs/006-backend-search/contracts/openapi.yaml
- [ ] T039 Accessibility pass: ensure banner & loading have aria roles resources/js/Components/Fish/FishSearchCursorErrorBanner.vue
- [ ] T040 Add CI matrix: ensure new tests run (update workflow if needed) .github/workflows/\*.yml
- [x] T041 Refactor duplicated FR-001 line removal confirmation (grep sanity) specs/006-backend-search/spec.md
- [ ] T042 Code comment references to spec IDs (FR-003, SC-006) in service/controller
- [ ] T043 [P] Add optional performance test (skip on CI) tests/Feature/FishSearch/PerformanceSampleTest.php
- [ ] T044 Final lint & type check (frontend/backend) package.json / composer.json scripts

## Dependencies Graph

User Story Order: US1 → US2
Foundational tasks (T008–T015) must complete before US1 tasks. US1 tasks provide base interface before multi-filter extension (US2).

## Parallel Execution Examples

- Early parallel: T006, T013, T014, T015 可與 T008–T012 同步（不同檔案無衝突）。
- US1 前端並行：T022–T025 與後端 T016–T021 可分工。
- US2 filters（T027–T031）可平行開發，最後再整合測試（T034）。
- Polish 階段 T038 與 T039 可並行。

## Independent Test Criteria per User Story

- US1: 1) 初次載入 20 筆 + pageInfo.hasMore 正確；2) 名稱搜尋模糊比對返回正確集合；3) 422 INVALID_CURSOR 顯示 Banner 並可重啟；4) Loading 指示每次請求期間可見；5) 空結果回傳 items=[] 且 hasMore=false。
- US2: 1) 任意多條件組合皆以 AND 呈現；2) 每個 filter 單獨作用不影響其它；3) 續載延續同一組合；4) 無重複與遺漏（抽樣驗證）；5) 不存在自動關鍵字推論（名稱/地點以外）。

## MVP Scope Suggestion

僅實作 US1（後端單欄位搜尋 + 分頁 + 422 處理 + 前端 Loading/Infinite Scroll + 基本測試）。在 US1 完成後即可提供可用搜尋體驗再擴充 US2。

## Format Validation

全部任務符合 `- [ ] T### [P?] [US#?] 描述 + 檔案路徑` 格式；無缺失。

Total Tasks: 44
Per Story: US1 = 11 (T016–T026), US2 = 9 (T027–T035)
Parallel Opportunities: ≥10（Setup/Foundational/前後端並行/Polish）
