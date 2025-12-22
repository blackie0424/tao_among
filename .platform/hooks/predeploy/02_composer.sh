#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Installing Composer Dependencies ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/staging"
cd $APP_DIR

# 檢查 composer.json 是否存在
if [ ! -f "composer.json" ]; then
    echo "ERROR: composer.json not found!"
    exit 1
fi

# 檢查 Composer 是否已安裝
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
    echo "✓ Composer installed"
fi

# 顯示 Composer 版本
composer --version

# 安裝依賴（生產環境優化）
echo "Installing dependencies..."
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-suggest

echo "✓ Composer dependencies installed"

# 顯示已安裝的套件數量
PACKAGE_COUNT=$(composer show --no-dev | wc -l)
echo "Installed packages: $PACKAGE_COUNT"

echo "========================================="
echo "=== Composer installation completed ==="
echo "========================================="