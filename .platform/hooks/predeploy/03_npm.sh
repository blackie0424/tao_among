#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Building Frontend Assets ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/staging"
cd $APP_DIR

# 檢查是否需要編譯前端資源
if [ ! -f "package.json" ]; then
    echo "No package.json found, skipping frontend build"
    exit 0
fi

echo "Found package.json, building frontend assets..."

# 檢查 Node.js 和 npm
if ! command -v node &> /dev/null; then
    echo "ERROR: Node.js is not installed!"
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo "ERROR: npm is not installed!"
    exit 1
fi

# 顯示版本
echo "Node.js version: $(node --version)"
echo "npm version: $(npm --version)"

# 安裝依賴
echo "Installing npm dependencies..."
npm ci --production

# 編譯資源
echo "Building assets..."
if grep -q "\"build\"" package.json; then
    npm run build
    echo "✓ Assets built successfully"
elif grep -q "\"production\"" package.json; then
    npm run production
    echo "✓ Assets built successfully"
else
    echo "No build script found in package.json, skipping build"
fi

# 清理 node_modules（可選，節省空間）
# echo "Cleaning up node_modules..."
# rm -rf node_modules

echo "========================================="
echo "=== Frontend build completed ==="
echo "========================================="