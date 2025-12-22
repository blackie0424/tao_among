#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Running Database Migrations ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/staging"
cd $APP_DIR

# 檢查是否為 leader 實例（在多實例環境中只執行一次）
# Elastic Beanstalk 會設定這個環境變數
LEADER_ONLY=${EB_IS_COMMAND_LEADER:-true}

if [ "$LEADER_ONLY" != "true" ]; then
    echo "Not a leader instance, skipping migrations"
    exit 0
fi

echo "This is the leader instance, running migrations..."

# 確認資料庫連線
echo "Testing database connection..."
php artisan db:show 2>/dev/null || echo "Could not show database info (this is okay)"

# 執行遷移
echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "✓ Migrations completed"

# 可選：執行 seeders（僅用於開發/測試環境）
# if [ "${APP_ENV:-production}" != "production" ]; then
#     echo "Running database seeders..."
#     php artisan db:seed --force --no-interaction
#     echo "✓ Seeders completed"
# fi

echo "========================================="
echo "=== Database migrations completed ==="
echo "========================================="