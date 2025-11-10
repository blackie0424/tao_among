# Phase 1 Data Model — Backend Server-Side Search (006-backend-search)

Date: 2025-11-09

## Minimal Response Shapes

### FishItem (Slim)

```
{
  id: int,          // PK
  name: string,     // fish.name
  image_url: string // derived accessor getImageUrlAttribute()
}
```

Source: Eloquent `Fish` model fields (`id`, `name`) + accessor `image_url`. All other attributes stripped (FR-002, SC-004).

### PageInfo

```
{
  hasMore: bool,        // lookahead > perPage ? true : false
  nextCursor: int|null  // last_id of previous batch; null when hasMore=false
}
```

Invariant: `hasMore=false => nextCursor=null` (FR-005 fixed-field policy).

### FishSearchResponse

```
{
  items: FishItem[],
  pageInfo: PageInfo
}
```

## Input Parameters (Query)

| Name              | Type    | Normalization                                                | Semantics                                          |
| ----------------- | ------- | ------------------------------------------------------------ | -------------------------------------------------- |
| name              | string? | Trim; empty -> ignored                                       | ILIKE '%term%' (FR-003)                            |
| tribe             | string? | Trim; empty -> ignored                                       | LOWER(tribe)=LOWER(:tribe) exact (FR-003)          |
| capture_location  | string? | Trim; empty -> ignored                                       | ILIKE '%term%' maps to column `location` (FR-003)  |
| capture_method    | string? | Trim; empty -> ignored                                       | ILIKE '%term%' (FR-003)                            |
| processing_method | string? | Trim; empty -> ignored                                       | ILIKE '%term%' (FR-003)                            |
| food_category     | string? | Trim; empty -> ignored                                       | LOWER(food_category)=LOWER(:value) (FR-003/FR-001) |
| perPage           | int?    | If !int or not in [1, per_page_max] -> per_page_default (20) | Batch size (FR-007, FR-013)                        |
| last_id           | int?    | Validate >0 integer; semantic checks (direction/stale)       | Cursor for next batch (FR-005/FR-006)              |

## Query Composition (Pseudo)

```
function search(filters, perPage, lastId?): ResultEnvelope {
  base = Fish::query();
  if filters.name: base->where('name', 'ILIKE', "%{$filters.name}%");
  if filters.tribe: base->whereRaw('LOWER(tribe) = LOWER(?)', [filters.tribe]); // via related table or denormalized column (TBD)
  if filters.capture_location: base->where('location', 'ILIKE', "%{$filters.capture_location}%");
  if filters.capture_method: base->where('capture_method', 'ILIKE', "%{$filters.capture_method}%");
  if filters.processing_method: base->where('processing_method', 'ILIKE', "%{$filters.processing_method}%");
  if filters.food_category: base->whereRaw('LOWER(food_category) = LOWER(?)', [filters.food_category]);
  if lastId: base->where('id', '<', lastId);
  rows = base->orderByDesc('id')->limit(perPage + 1)->get();
  hasMore = rows.count() > perPage;
  items = slice first perPage rows;
  nextCursor = hasMore ? items.last().id : null;
  return { items: mapSlim(items), pageInfo: { hasMore, nextCursor } };
}
```

Note: Actual implementation may use parameter binding and avoid manual string interpolation. Tribe & food_category might reside in `tribal_classifications` table; if so a JOIN with DISTINCT may be needed — prefer denormalized columns if present to keep query simple (MVP principle). If JOIN required:

```
base->join('tribal_classifications as tc', 'tc.fish_id', '=', 'fish.id')
     ->whereRaw('LOWER(tc.tribe)=LOWER(?)')
```

Ensure index support on joined columns if frequency grows.

## Cursor Semantics Validation

- Non-numeric / <=0: Reject 422
- Direction error: Provided last_id >= previous nextCursor (tracked client-side) → service double-check by verifying existence of any row with id >= last_id within current filter set (optional optimization). Simpler: trust client and only validate format (MVP) then rely on empty vs invalid distinction — but spec demands 422 for direction error. Proposed check:

```
if previousNextCursor && last_id >= previousNextCursor: error INVALID_CURSOR
```

Previous cursor value can be passed by controller (from request context). For stateless server we rely on client-provided last_id only; direction error detect by querying if any row with id >= last_id and filters produce overlap while expecting strictly smaller. Minimal approach: treat >= last_id of first page highest id as direction error if filters fixed.

## Slim Mapping Logic

```
function mapSlim(fishModel): FishItem {
  return [
    'id' => $fishModel->id,
    'name' => $fishModel->name,
    'image_url' => $fishModel->image_url, // accessor executed
  ];
}
```

No other attributes or relations loaded (FR-009).

## Error Shape

```
422 INVALID_CURSOR => { error: 'INVALID_CURSOR', message?: 'Invalid or stale last_id.' }
```

Message optional (FR-006); error enum stable.

## Edge Case Handling Summary

| Case                             | Behavior                                                         |
| -------------------------------- | ---------------------------------------------------------------- |
| Empty first page                 | 200 items=[] hasMore=false nextCursor=null                       |
| Large last_id (beyond smallest)  | 200 empty (tail)                                                 |
| Stale cursor after filter change | 422 INVALID_CURSOR (client triggers by reusing old last_id)      |
| Deleted ids between batches      | No impact; id < last_id still returns valid remaining rows       |
| Rapid double request             | Frontend responsibility; backend id filter ensures no duplicates |

## Future Considerations (Not in MVP)

- Opaque cursor encoding with checksum to prevent misuse.
- Adding composite GIN index for fuzzy fields if latency fails SC-002.
- Partitioning or caching layer for high scale.

Status: READY FOR QUICKSTART
