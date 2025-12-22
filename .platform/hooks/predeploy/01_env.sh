#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Generating .env from EBS Environment Variables ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/staging"
ENV_FILE="$APP_DIR/.env"
EB_ENV_FILE="/opt/elasticbeanstalk/deployment/env"

# 檢查目錄
if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: Directory $APP_DIR does not exist!"
    exit 1
fi

echo "Target directory: $APP_DIR"
echo "Target .env file: $ENV_FILE"
echo ""

# === 方法 1: 直接從 EB 環境變數檔案讀取 ===
echo "=== Reading from EBS deployment file ==="
echo "Source file: $EB_ENV_FILE"

if [ ! -f "$EB_ENV_FILE" ]; then
    echo "WARNING: $EB_ENV_FILE does not exist"
    echo "This means no environment variables are configured in EBS Console"
    echo ""
    echo "Creating minimal .env file..."
    
    # 創建基本的 .env 檔案
    cat > $ENV_FILE << 'EOF'
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=http://localhost
EOF
    
    chown webapp:webapp $ENV_FILE
    chmod 644 $ENV_FILE
    
    echo "⚠ Created default .env file"
    echo "⚠ IMPORTANT: Configure environment variables in EBS Console!"
    echo "   Path: Configuration → Software → Environment properties"
    echo ""
    exit 0
fi

# 顯示檔案資訊
echo "✓ Found EBS environment file"
LINE_COUNT=$(wc -l < $EB_ENV_FILE)
echo "  - Lines: $LINE_COUNT"

if [ $LINE_COUNT -eq 0 ]; then
    echo "WARNING: EBS environment file is empty"
    echo "No environment variables configured"
    exit 1
fi

# 直接複製檔案
echo ""
echo "Copying environment variables..."
cp $EB_ENV_FILE $ENV_FILE

# 驗證複製結果
if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: Failed to create .env file"
    exit 1
fi

COPIED_LINES=$(wc -l < $ENV_FILE)
COPIED_SIZE=$(stat -c%s "$ENV_FILE" 2>/dev/null || stat -f%z "$ENV_FILE")

echo "✓ .env file created successfully"
echo "  - Location: $ENV_FILE"
echo "  - Lines: $COPIED_LINES"
echo "  - Size: $COPIED_SIZE bytes"

# 顯示變數名稱（不含值）
if [ $COPIED_LINES -gt 0 ]; then
    echo ""
    echo "Environment variables (first 10):"
    head -10 $ENV_FILE | cut -d'=' -f1 | sed 's/^/  - /'
    
    if [ $COPIED_LINES -gt 10 ]; then
        echo "  ... and $((COPIED_LINES - 10)) more"
    fi
fi

# 設定權限
chown webapp:webapp $ENV_FILE
chmod 644 $ENV_FILE
echo ""
echo "✓ Permissions set (webapp:webapp, 644)"

echo ""
echo "========================================="
echo "=== .env generation completed successfully ==="
echo "========================================="