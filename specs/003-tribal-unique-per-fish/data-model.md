# Data Model — Tribal classification unique per fish

## Entities

- Fish (existing)
- TribalClassification (existing)
  - Fields: id, fish_id (FK fish), tribe (enum: {iraraley, ivalino, iranmeilek, yayo, iratay, imorod}), food_category, processing_method, notes, timestamps, deleted_at
  - Indexes: UNIQUE (fish_id, tribe)

## Validation

- tribe ∈ {iraraley, ivalino, iranmeilek, yayo, iratay, imorod}（從 config 載入）
- notes ≤ 1000 chars
- food_category, processing_method optional text fields

## Migrations

- Add unique index on (fish_id, tribe)
- Optional: migration script for deduplication — 僅標記重複待人工（不自動合併）

## Relationships

- Fish hasMany TribalClassification
- TribalClassification belongsTo Fish
