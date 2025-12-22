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

# === 重點修改：安裝所有依賴（包含 devDependencies） ===
echo "Installing npm dependencies (including devDependencies for build)..."
npm ci

echo "✓ Dependencies installed"

# 顯示已安裝的套件數量
PACKAGE_COUNT=$(npm list --depth=0 2>/dev/null | grep -c "├──\|└──" || echo "unknown")
echo "Installed packages: $PACKAGE_COUNT"

# 編譯資源
echo ""
echo "Building assets with Vite..."

if grep -q "\"build\"" package.json; then
    npm run build
    echo "✓ Assets built successfully"
elif grep -q "\"production\"" package.json; then
    npm run production
    echo "✓ Assets built successfully"
else
    echo "WARNING: No build script found in package.json"
    echo "Available scripts:"
    npm run 2>&1 | grep -E "^\s+" || echo "None"
    exit 1
fi

# === 清理 node_modules 節省空間（可選） ===
echo ""
echo "Cleaning up development dependencies..."

# 重新安裝，但這次只安裝 production 依賴
npm prune --production

echo "✓ Development dependencies removed"
echo "Remaining packages:"
PROD_PACKAGE_COUNT=$(npm list --depth=0 --production 2>/dev/null | grep -c "├──\|└──" || echo "unknown")
echo "  - Production: $PROD_PACKAGE_COUNT"

echo ""
echo "========================================="
echo "=== Frontend build completed ==="
echo "========================================="