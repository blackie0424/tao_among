# Phase 0 — Research (Refactor FishController)

Decision: Read-only scope, service extraction, null-safe media URLs, avoid N+1, serverless-safe logging.

Rationale:

- Reduce controller responsibilities; centralize media URL logic to service for reuse/testability.
- Prevent exceptions from null filenames (e.g., Supabase getUrl) by guarding calls.
- Use eager loading to avoid N+1.
- Serverless deploys often have read-only FS; use `errorlog`.

Alternatives considered:

- Keep logic in controller — rejected: hard to test, increases coupling.
- Global middleware for media URLs — rejected: mixes presentation with transport.
- Force default audio URL — rejected: misrepresents missing audio; null conveys absence.

Open Items: none (Q1/Q2/Q3 resolved in spec).
