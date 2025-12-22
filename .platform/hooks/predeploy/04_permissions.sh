#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Setting File Permissions ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/staging"
cd $APP_DIR

# Laravel 需要寫入權限的目錄
WRITABLE_DIRS=(
    "storage"
    "storage/app"
    "storage/app/public"
    "storage/framework"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
)

echo "Setting permissions for writable directories..."

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        chmod -R 775 "$dir"
        chown -R webapp:webapp "$dir"
        echo "✓ $dir"
    else
        echo "⚠ $dir does not exist, creating..."
        mkdir -p "$dir"
        chmod -R 775 "$dir"
        chown -R webapp:webapp "$dir"
        echo "✓ $dir created and configured"
    fi
done

# 確保 .env 檔案權限正確
if [ -f ".env" ]; then
    chmod 644 .env
    chown webapp:webapp .env
    echo "✓ .env permissions set"
fi

echo "========================================="
echo "=== File permissions set successfully ==="
echo "========================================="