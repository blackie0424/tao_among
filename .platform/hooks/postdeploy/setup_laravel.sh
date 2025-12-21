#!/bin/bash
set -e

echo "===== Laravel 應用程式設定 ====="

cd /var/app/current

# 1. 載入 EBS 環境變數
echo "1️⃣ 載入環境變數..."
if [ -f /opt/elasticbeanstalk/deployment/env ]; then
    set -a
    source /opt/elasticbeanstalk/deployment/env
    set +a
    echo "✅ 環境變數已載入"
    
    # 驗證關鍵變數
    if [ -z "$APP_KEY" ]; then
        echo "❌ APP_KEY 未設定"
        exit 1
    fi
    echo "APP_KEY: ${APP_KEY:0:20}..."
    
    if [ -z "$DB_HOST" ]; then
        echo "❌ DB_HOST 未設定"
        exit 1
    fi
    echo "DB_HOST: $DB_HOST"
else
    echo "❌ 環境變數檔案不存在"
    exit 1
fi

# 2. 修正檔案權限
echo "2️⃣ 修正檔案權限..."
sudo chown -R webapp:webapp storage bootstrap/cache
sudo chmod -R 755 storage bootstrap/cache
echo "✅ 檔案權限已修正"

# 3. 清除舊快取
echo "3️⃣ 清除 Laravel 快取..."
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 4. 執行資料庫遷移
echo "4️⃣ 執行資料庫遷移..."
php artisan migrate --force

# 5. 重建快取
echo "5️⃣ 重建 Laravel 快取..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. 建立 storage link
echo "6️⃣ 建立 storage link..."
php artisan storage:link || true

# 7. 重啟 PHP-FPM
echo "7️⃣ 重啟 PHP-FPM..."
sudo systemctl restart php-fpm

# 8. 驗證設定
echo "8️⃣ 驗證 Laravel 設定..."
php artisan about

echo "===== Laravel 應用程式設定完成 ====="