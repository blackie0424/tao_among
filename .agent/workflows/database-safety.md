---
description: 資料庫操作安全規則
---

# 資料庫操作安全規則

## 需要明確授權的指令

以下指令會影響資料庫資料，**必須先詢問使用者並得到明確授權**後才能執行：

1. `php artisan migrate:fresh` - 會刪除所有資料表並重建
2. `php artisan migrate:refresh` - 會回滾並重新執行所有 migration
3. `php artisan migrate:reset` - 會回滾所有 migration
4. `php artisan db:wipe` - 會刪除所有資料表
5. 任何包含 `--force` 的 migrate 指令

## 可以安全執行的指令

以下指令相對安全，可以直接執行：

1. `php artisan migrate` - 只執行未執行的 migration（不會刪除現有資料）
2. `php artisan migrate:status` - 查看 migration 狀態
3. `php artisan db:seed --class=XXX` - 執行特定 seeder（需確認不會覆蓋資料）

## 測試相關

- 執行測試前確認 `phpunit.xml` 中的 `DB_DATABASE` 設定為 `:memory:`
- 避免測試影響到主資料庫

## 2026-02-02 事件記錄

由於 `phpunit.xml` 中的資料庫設定被註解，導致執行單元測試時使用了主資料庫，`RefreshDatabase` trait 清空了所有資料。已修正 `phpunit.xml` 設定。
