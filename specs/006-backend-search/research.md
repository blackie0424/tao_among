# Phase 0 Research — Backend Server-Side Search (006-backend-search)

Date: 2025-11-09
Spec: `specs/006-backend-search/spec.md`

## Objectives

- Validate chosen pagination pattern (`id DESC` + numeric last_id) suffices for non-gap stability after deletions and insertions.
- Confirm query strategy for multi-field AND filters with case-insensitive rules (ILIKE / LOWER=LOWER for tribe & food_category).
- Establish test matrix before implementation (red → green path).

## Decisions (Derived from Spec Clarifications)

1. Cursor Encoding: Plain integer last_id (FR-005) — keep direct numeric to reduce complexity and ease debugging.
2. perPage Normalization: Normalize all invalid/out-of-range values to `per_page_default` (20); no clamp (FR-007, FR-013).
3. Tribe & Food Category Matching: Case-insensitive exact equality via `LOWER(column) = LOWER(:value)` (FR-003); forbid fuzzy/wildcards.
4. Fuzzy Fields: `name`, `capture_location`, `capture_method`, `processing_method` use `ILIKE '%term%'` (FR-003)
5. Pagination Query Pattern:
   - First page: `SELECT ... FROM fish WHERE <filters> ORDER BY id DESC LIMIT perPage+1`.
   - Next pages: `SELECT ... FROM fish WHERE <filters> AND id < :last_id ORDER BY id DESC LIMIT perPage+1`.
6. Lookahead: Use `perPage+1` to determine `hasMore` and compute `nextCursor` from the Nth row (index perPage-1). (PageInfo section)
7. Empty Segment vs Invalid Cursor: Empty legitimate segment → 200 with `items=[]`; semantic invalidity (non-int, <=0, direction wrong, stale after filter change) → 422 `{error:INVALID_CURSOR}` (FR-006 table).
8. Slow Query Threshold: `slow_query_ms=1000` constant only; no persistent logging (FR-011); optional local timing hooks via comments.
9. Public Endpoint Security: Restrict fields to `id,name,image_url` to avoid exposing sensitive/internal data (FR-002, FR-015).

## Open Questions (Resolved)

- Need for offset fallback? → Rejected to maintain stability and performance. (FR-005 rationale)
- Need for recommended terms on empty results? → Rejected (FR-010) for simplicity & payload control.

## Risks & Mitigations

| Risk                                        | Impact               | Mitigation                                                                                     |
| ------------------------------------------- | -------------------- | ---------------------------------------------------------------------------------------------- |
| Misuse of old cursor after filters change   | 422 loops for client | Clear 422 spec & test case (stale cursor) + front-end resets state                             |
| Performance degradation with multiple ILIKE | Higher latency       | Add composite index review later if SC-002 p95 risk; monitor locally timing vs `slow_query_ms` |
| Large perPage attempts (e.g., 500)          | Server load spike    | Normalization to 20 (no clamp) prevents expansion                                              |
| Race conditions in rapid scroll             | Duplicate queries    | Frontend should lock loading state; Service id < last_id ensures no duplication                |

## Test Matrix (Pest Feature Tests Draft)

1. First page, no filters: 200, items count <= perPage, hasMore correct.
2. Name fuzzy filter: 200, all names ILIKE '%term%'.
3. Tribe exact filter: 200, all tribe LOWER=term.
4. Combined name + tribe + capture_location: AND correctness.
5. perPage invalid (51, 0, -1, 'abc'): normalized to default (assert number of items <=20 and >0 when data exists).
6. Cursor happy path second page: nextCursor numeric & items id < previous last_id.
7. Cursor pointing empty tail: 200 empty items, hasMore=false, nextCursor=null.
8. Invalid cursor non-numeric: 422 INVALID_CURSOR.
9. Invalid cursor non-positive: 422 INVALID_CURSOR.
10. Direction error (use artificially higher id than previous nextCursor): 422 INVALID_CURSOR.
11. Stale cursor after filter change: request with old last_id + changed filter → 422.
12. Payload slimness: ensure item only has id,name,image_url (no unexpected fields).

## Implementation Outline (Pre-Code)

- Add new `FishSearchRequest` validating query params + semantic cursor check (call service to validate direction if last_id given).
- Introduce `FishSearchService` building a dynamic query with conditional WHERE clauses.
- Incorporate perPage normalization logic inside Request (return normalized value to controller).
- Query execution + lookahead in Service returning DTO-like array (no new class) to Controller.
- Controller transforms rows to slim array (map to image_url attribute). Avoid loading relations.

## Abstractions Justification

- Service layer reduces controller complexity and isolates the query for unit testing (Test-First principle).
- Request object centralizes input sanitation & error responses (422) — enforces Contract-First determinism.

## Next Steps → Phase 1

- Formalize data model (Fish minimal projection + pageInfo).
- Document query composition pseudo-code.
- Provide quickstart consumer usage (frontend integration notes).

## Exit Criteria Phase 0

- All decisions trace to FR/SC.
- Test Matrix complete & covers invalid cursor semantics.
- No remaining OPEN questions.

Status: READY FOR PHASE 1
