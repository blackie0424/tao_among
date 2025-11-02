# Implementation Plan — 001-audio-upload-confirm

Updated: 2025-11-01

## Goals
- Two-step upload for audio: sign (to pending/) → client PUT → confirm (transactional DB + move to audio/)
- Idempotent confirm (Option A): repeat calls return 200 with current state
- Cleanup: TTL-based purge of pending/ and compensating deletes on failures

## Architecture
- Storage layout
  - pending/audio/{date}/{uuid}.{ext}
  - audio/{fishId}/{uuid}.{ext} (or other deterministic final naming)
- Services
  - SupabaseStorageService
    - new: createSignedUploadUrlForPendingAudio(fishId): returns absolute signed URL (pending path)
    - new: moveObject(src, dest): move/rename in bucket
    - new: listAndPurge(prefix, olderThan): for TTL cleanup (optional service surface)
- Controllers
  - UploadController
    - POST /prefix/api/upload/audio/sign → returns signed URL (pending/ path)
    - POST /prefix/api/upload/audio/confirm → validates object exists in pending/, transactional create FishAudio + update Fish, move to audio/, return 200 (idempotent)
- Transactions
  - confirm performs: validate → beginTransaction → move pending → audio (or move after DB?) → write DB → commit
  - Compensation: if DB fails after move, attempt delete audio/{...}
  - Ordering choice:
    - Option 1 (move first): ensures file exists at final location when DB writes; needs compensation delete on DB failure
    - Option 2 (DB first then move): risk DB commit but move fails; requires rollback or mark as inconsistent
    - Choose Option 1 + compensation (preferred in object storage flows)

## API Contracts (sketch)
- POST /prefix/api/upload/audio/sign
  - input: { fish_id }
  - output: { uploadUrl, filePath, expiresIn }
  - behavior: returns pending path signed URL
- POST /prefix/api/upload/audio/confirm
  - input: { fish_id, filePath, originalName?, durationMs? }
  - behavior:
    - If object exists at pending/filePath:
      - begin transaction
      - move pending → audio final path
      - upsert FishAudio row; set fish.audio_filename if needed; emit URL via storage service
      - commit; return 200 with state
    - If already confirmed for same idempotency key (fish_id+filePath): return 200 with current state
    - If object missing: return 409 (or 404) with reason

## Data Model
- FishAudio (existing)
  - fields to verify: fish_id, filename (final), duration, mime, size, created_at
  - add index on (fish_id, filename)
- Idempotency identity
  - fish_id + pending filePath used to gate confirm
  - Store mapping on confirm? Option: derive by checking a temporary marker table if needed; or infer by searching for final filename created from the pending key

## Testing Strategy
- Unit (Pest)
  - SupabaseStorageService: pending path generation; moveObject happy/failure
- Feature (Pest)
  - sign returns pending path absolute URL
  - confirm happy path: creates DB records, moves file, returns 200 with URL
  - confirm idempotent: second call returns 200 and no duplicates
  - confirm missing object: returns 409, does not write DB
  - transaction failure: simulate DB failure after move, compensating delete attempted
- Frontend (Vitest) — lightweight
  - ensure sign→PUT→confirm wiring; duplicate confirm handled gracefully

## Operations
- Scheduled Cleanup
  - Laravel command: `audio:purge-pending --ttl=3600`
  - Cron/scheduler entry in Laravel kernel
- Observability
  - log key steps and errors with correlationId (fish_id, filePath)

## Rollout
- Feature flag optional (not required)
- Backfill/compatibility: existing audio flow remains until frontend switches

## Risks & Mitigations
- Race: concurrent confirms on same key → lock by unique index or catch duplicate and return 200
- Partial failures: rely on compensation delete; surface metrics
