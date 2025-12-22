#!/bin/bash
set -euo pipefail

exec 1> >(logger -s -t $(basename $0)) 2>&1

echo "========================================="
echo "=== Generating .env from EBS Environment Variables ==="
echo "=== Time: $(date '+%Y-%m-%d %H:%M:%S') ==="
echo "========================================="

APP_DIR="/var/app/staging"
ENV_FILE="$APP_DIR/.env"

# 檢查目錄
if [ ! -d "$APP_DIR" ]; then
    echo "ERROR: Directory $APP_DIR does not exist!"
    exit 1
fi

echo "Target directory: $APP_DIR"
echo "Target .env file: $ENV_FILE"

# 使用 Python 生成 .env 檔案
echo "Parsing EBS environment variables..."
/opt/elasticbeanstalk/bin/get-config environment | python3 -c "
import sys
import json

try:
    data = json.load(sys.stdin)
    print(f'Found {len(data)} environment variables', file=sys.stderr)
    
    for key, value in data.items():
        # 處理包含特殊字元的值
        value_str = str(value)
        # 如果值包含空格或特殊字元，用引號包起來
        if ' ' in value_str or any(c in value_str for c in ['#', '$', '\"']):
            value_str = f'\"{value_str}\"'
        print(f'{key}={value_str}')
        
except Exception as e:
    print(f'ERROR: Failed to parse JSON: {e}', file=sys.stderr)
    sys.exit(1)
" > $ENV_FILE

# 驗證檔案生成
if [ -f "$ENV_FILE" ]; then
    LINE_COUNT=$(wc -l < $ENV_FILE)
    FILE_SIZE=$(stat -c%s "$ENV_FILE" 2>/dev/null || stat -f%z "$ENV_FILE")
    
    echo "✓ .env file created successfully"
    echo "  - Lines: $LINE_COUNT"
    echo "  - Size: $FILE_SIZE bytes"
    echo "  - Location: $ENV_FILE"
    
    # 顯示變數名稱（不含值）
    echo "Environment variables:"
    cut -d'=' -f1 $ENV_FILE | head -10 | sed 's/^/  - /'
    if [ $LINE_COUNT -gt 10 ]; then
        echo "  ... and $((LINE_COUNT - 10)) more"
    fi
    
    # 設定權限
    chown webapp:webapp $ENV_FILE
    chmod 644 $ENV_FILE
    echo "✓ Permissions set (webapp:webapp, 644)"
else
    echo "ERROR: Failed to create .env file"
    exit 1
fi

echo "========================================="
echo "=== .env generation completed ==="
echo "========================================="