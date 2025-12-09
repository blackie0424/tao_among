#!/bin/bash
set -euo pipefail

LOG=/var/log/eb-hooks-frontend-build.log
echo "=== frontend build start: $(date -u) ===" >> "$LOG"

APP_DIR="/var/app/current"

if [ ! -d "$APP_DIR" ]; then
  echo "ERROR: $APP_DIR does not exist" >> "$LOG"
  exit 1
fi

cd "$APP_DIR"
echo "PWD: $(pwd)" >> "$LOG"

# 檢查 node 是否存在
if command -v node >/dev/null 2>&1; then
  echo "Node found: $(node -v)" >> "$LOG"
else
  echo "Node not found, installing..." >> "$LOG"
  curl -fsSL https://rpm.nodesource.com/setup_20.x | bash - >> "$LOG" 2>&1
  yum install -y nodejs >> "$LOG" 2>&1
fi

echo "Installing npm packages..." >> "$LOG"
npm ci >> "$LOG" 2>&1

echo "Running build..." >> "$LOG"
npm run build >> "$LOG" 2>&1

echo "=== frontend build end: $(date -u) ===" >> "$LOG"
