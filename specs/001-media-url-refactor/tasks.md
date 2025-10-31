# Tasks: Media URL Refactor (001)

This tasks list operationalizes the spec in `spec.md` and plan in `plan.md`. Each task has an ID, dependencies, and acceptance notes. Parallelizable tasks are marked.

## Legend
- Status: [ ] TODO / [x] Done
- Parallel: (P)

## Tasks

- [x] T001 - Centralize media URL builder
  - Desc: Implement `SupabaseStorageService::getUrl(type, filename, ?hasWebp)`; early return if full URL; build audios under `audio/`; images use `has_webp` when true for `webp/` else original `images/`. Remove all runtime HEAD checks.
  - Deps: none
  - Acceptance: Unit smoke call for images/audios; ensure no HTTP HEAD occurs.

- [x] T002 - Update call sites to pass has_webp
  - Desc: Update `FishService`, `CaptureRecord` accessor, `FishNoteController::create()` to forward `has_webp`.
  - Deps: T001
  - Acceptance: Fish list/detail shows correct URL and default fallback; no double-prefix for absolute URLs.

- [x] T003 - Ensure UploadController returns absolute signed URLs
  - Desc: Signed upload endpoints must return absolute `url` from service; audio signing must only persist DB after successful signing.
  - Deps: T001
  - Acceptance: Feature tests for signed-upload endpoints pass.

- [x] T004 - Tests: adjust/add unit + feature
  - Desc: Add/adjust tests per FRs: no HEAD, has_webp null/false -> original; absolute URL passthrough; audio omission when null/empty.
  - Deps: T001-T003
  - Acceptance: `./vendor/bin/pest` green for Unit + Feature (excluding legacy). [Current status: green]

- [ ] T005 - Docs: update spec references
  - Desc: Update `spec.md` success criteria notes with "no HEAD" and clarify absolute URL contract if not already.
  - Deps: T001-T004
  - Acceptance: Docs reflect final behavior.

## Notes
- Edge cases covered: full URL passthrough; empty image -> default; empty audio -> omit URL.
- Follow-up: consider adding a small helper to hide `has_webp` internally if needed.
