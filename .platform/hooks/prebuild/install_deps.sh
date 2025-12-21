#!/bin/bash
set -e

echo "===== 安裝相依套件 ====="

# 安裝 Node.js（若尚未安裝）
if [ ! -f /usr/bin/node ]; then
    echo "安裝 Node.js 20.x..."
    curl -sL https://rpm.nodesource.com/setup_20.x | sudo bash -
    sudo yum install -y nodejs
    echo "✅ Node.js 已安裝"
fi

# 安裝 Composer 相依套件
cd /var/app/staging
echo "安裝 Composer 相依套件..."
composer install --no-dev --optimize-autoloader --no-interaction
echo "✅ Composer 相依套件已安裝"

# 安裝 npm 相依套件
echo "安裝 npm 相依套件..."
npm ci --production=false
echo "✅ npm 相依套件已安裝"

# 建構前端資產
echo "建構前端資產..."
npm run build
if [ ! -d "public/build" ]; then
    echo "❌ 前端建構失敗"
    exit 1
fi
echo "✅ 前端資產建構完成"

echo "===== 相依套件安裝完成 ====="