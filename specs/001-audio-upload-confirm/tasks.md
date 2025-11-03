# Tasks — 001-audio-upload-confirm

Updated: 2025-11-01

Feature Name: Audio Upload Confirm

Note: Tasks are organized by phases per user stories from `spec.md`. Strict checklist format is applied.

## Phase 1 — Setup

- [X] T001 Create OpenAPI contract for audio sign/confirm at specs/001-audio-upload-confirm/contracts/audio-upload.openapi.yaml
- [X] T002 Add new flow overview to README at README.md

## Phase 2 — Foundational

- [X] T003 Implement pending sign method in storage service at app/Services/SupabaseStorageService.php
- [X] T004 Implement moveObject(src, dest) in storage service at app/Services/SupabaseStorageService.php
- [X] T005 Create purge helper (listAndPurge or PurgeService) at app/Services/PurgeService.php
- [X] T006 Create migration for unique index on fish audios (fish_id, filename) at database/migrations/xxxx_xx_xx_xxxxxx_add_unique_index_to_fish_audios.php

## Phase 3 — User Story 1 (P1): 成功上傳並保存

Story goal: 完成 sign → PUT → confirm 成功流程，持久化到 DB 並提供可用 URL。
Independent test: 端到端呼叫後 API 回傳 200、DB 生成 FishAudio 並更新 Fish.audio_filename，URL 可用。

- [X] T007 [US1] Add audio sign/confirm routes at routes/api.php
- [X] T008 [US1] Implement POST /prefix/api/upload/audio/sign in controller at app/Http/Controllers/UploadController.php
- [X] T009 [US1] Implement POST /prefix/api/upload/audio/confirm (transactional + move + persist) at app/Http/Controllers/UploadController.php
- [X] T010 [P] [US1] Ensure FishAudio model fillables/casts cover new fields at app/Models/FishAudio.php
- [X] T011 [US1] Feature test: happy path sign→confirm returns 200 and persists at tests/Feature/AudioUpload/SignConfirmHappyPathTest.php

## Phase 4 — User Story 2 (P1): 任何一步失敗時不落資料

Story goal: 失敗情境不產生資料殘留，若已搬移則補償刪除。
Independent test: 簽名失敗、PUT 失敗或 pending 檔案不存在時，DB 無異動；必要時補償刪除成功。

- [X] T012 [US2] Confirm checks pending object existence and returns 409/404 at app/Http/Controllers/UploadController.php
- [X] T013 [P] [US2] Implement best-effort compensation delete on post-move failure at app/Http/Controllers/UploadController.php
- [X] T014 [US2] Feature test: sign failure and PUT failure produce no DB writes at tests/Feature/AudioUpload/FailurePathsTest.php
- [X] T015 [P] [US2] Unit test: moveObject failure handling at tests/Unit/SupabaseStorageServiceMoveTest.php

## Phase 5 — User Story 3 (P2): 前端確認回傳（冪等）

Story goal: confirm 冪等（Option A）：重複 confirm 回 200 並返回現況，不重覆寫入。
Independent test: 重複呼叫 confirm 對同一 fish_id + filePath，API 穩定回 200、DB 無重覆紀錄。

- [X] T016 [US3] Add idempotency guard by fish_id + filePath in confirm path at app/Http/Controllers/UploadController.php
- [X] T017 [P] [US3] Extract confirm orchestration logic into service (optional) at app/Services/AudioConfirmService.php
- [X] T018 [US3] Feature test: idempotent confirm returns 200 with current state at tests/Feature/AudioUpload/IdempotentConfirmTest.php
- [X] T019 [P] [US3] Update API docs for idempotent behavior at specs/001-audio-upload-confirm/contracts/audio-upload.openapi.yaml

## Phase 6 — Polish & Operations

- [X] T020 Create console command to purge pending with TTL at app/Console/Commands/PurgePendingAudio.php
- [X] T021 Register purge command in scheduler kernel at app/Console/Kernel.php
- [X] T022 [P] Add configurable TTL for pending purge at config/audio.php
- [X] T023 [P] Add structured logging for sign/confirm with correlation IDs at app/Http/Controllers/UploadController.php
- [X] T024 Update README quickstart for new flow at README.md

## Dependencies (story order)

1. US1 → 2. US2 → 3. US3

## Parallel execution examples

- T010, T015, T017, T019, T022, T023 可平行執行（不同檔案、無相互依賴）。

## Implementation strategy

- MVP: 完成 Phase 3（US1）即可交付可用上傳確認流程；接著補上 US2 的失敗保護與 US3 的冪等。
