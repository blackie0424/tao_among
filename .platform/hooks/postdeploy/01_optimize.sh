#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Running Laravel Optimization ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/current"
cd $APP_DIR

# 清除所有快取
echo "Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
echo "✓ Caches cleared"

# 生產環境優化：快取配置
echo "Caching for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✓ Production caches created"

# 如果使用 Laravel Horizon（Redis 隊列）
if [ -f "artisan" ] && php artisan list | grep -q "horizon:terminate"; then
    echo "Terminating Horizon..."
    php artisan horizon:terminate
    echo "✓ Horizon will restart automatically"
fi

# 重啟隊列 workers
if php artisan list | grep -q "queue:restart"; then
    echo "Restarting queue workers..."
    php artisan queue:restart
    echo "✓ Queue workers restarted"
fi

# 建立 storage 符號連結（如果尚未建立）
if [ ! -L "public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
    echo "✓ Storage link created"
fi

echo "========================================="
echo "=== Laravel optimization completed ==="
echo "========================================="