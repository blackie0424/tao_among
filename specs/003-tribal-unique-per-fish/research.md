# Research — Tribal classification unique per fish

## Decisions

1) Fixed tribe list source → config/tribes.php (array of allowed values)
- Rationale: 易於維護與前後端共用；不牽涉 DB 變更
- Alternatives: Enum 類別（需 PHP 8.1+）、資料表（需要 seed 與 join）；均可但現階段簡單優先
 - Values/Labels: iraraley→朗島, ivalino→野銀, iranmeilek→東清, yayo→椰油, iratay→漁人, imorod→紅頭

2) Duplicate migration strategy → 僅標記重複待人工（不自動合併）
- Rationale: 由人員審視確保語意正確；避免自動合併誤解
- Alternatives: 自動合併備註；風險是語境混淆

3) DELETE 端點 → 先不提供
- Rationale: MVP 聚焦 GET/POST/PUT；刪除可在後續補充
- Alternatives: 必須提供；若流程要求可在 Phase 2 規劃

## Open Questions Resolved

- Notes 長度：建議 1000 chars 上限；允許基本標點與換行
- Concurrency：唯一索引 + 後端驗證雙保險

## Implementation Notes (non-binding)

- Migration: addUnique('tribal_classifications', ['fish_id','tribe'])
- Request validation: in:allowed_tribes + unique constraint check (fish_id,tribe)
- Controller: upsert flow should fail on duplicate create; suggest using PUT for updates
