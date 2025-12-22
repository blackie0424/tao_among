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

# === 診斷：檢查 get-config 命令 ===
echo ""
echo "=== Diagnostic: Testing get-config command ==="

# 測試命令是否存在
if ! command -v /opt/elasticbeanstalk/bin/get-config &> /dev/null; then
    echo "ERROR: get-config command not found!"
    exit 1
fi
echo "✓ get-config command exists"

# 獲取環境變數並儲存到臨時檔案
TEMP_JSON="/tmp/eb-env-config.json"
echo "Fetching environment variables..."
/opt/elasticbeanstalk/bin/get-config environment > $TEMP_JSON 2>&1

# 顯示原始輸出（用於除錯）
echo "Raw output from get-config:"
cat $TEMP_JSON
echo ""

# 檢查檔案大小
FILE_SIZE=$(stat -c%s "$TEMP_JSON" 2>/dev/null || stat -f%z "$TEMP_JSON")
echo "Output size: $FILE_SIZE bytes"

# 如果檔案為空或太小
if [ $FILE_SIZE -lt 2 ]; then
    echo "WARNING: get-config returned empty or nearly empty output"
    echo "This usually means no environment variables are configured in EBS Console"
    echo ""
    echo "Creating minimal .env file with defaults..."
    
    # 創建基本的 .env 檔案
    cat > $ENV_FILE << 'EOF'
APP_NAME=Laravel
APP_ENV=production
APP_DEBUG=false
APP_KEY=
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=root
DB_PASSWORD=

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
EOF
    
    chown webapp:webapp $ENV_FILE
    chmod 644 $ENV_FILE
    
    echo "⚠ Created default .env file"
    echo "⚠ IMPORTANT: Please configure environment variables in EBS Console!"
    echo ""
    echo "To configure: EBS Console → Configuration → Software → Environment properties"
    
    exit 0
fi

# 驗證 JSON 格式
echo "Validating JSON format..."
if ! python3 -c "import sys, json; json.load(open('$TEMP_JSON'))" 2>/dev/null; then
    echo "ERROR: Output is not valid JSON"
    echo "Content:"
    cat $TEMP_JSON
    exit 1
fi
echo "✓ JSON format is valid"

# === 解析並生成 .env 檔案 ===
echo ""
echo "Parsing EBS environment variables..."

python3 << 'PYTHON_SCRIPT'
import sys
import json

try:
    # 讀取臨時 JSON 檔案
    with open('/tmp/eb-env-config.json', 'r') as f:
        data = json.load(f)
    
    if not data:
        print('WARNING: No environment variables found in EBS configuration', file=sys.stderr)
        sys.exit(0)
    
    print(f'Found {len(data)} environment variables', file=sys.stderr)
    
    # 生成 .env 內容
    env_lines = []
    for key, value in sorted(data.items()):
        value_str = str(value)
        
        # 處理特殊字元
        needs_quotes = False
        if any(c in value_str for c in [' ', '#', '"', "'", '$', '`', '\\', '\n', '\t']):
            needs_quotes = True
            # 轉義雙引號
            value_str = value_str.replace('"', '\\"')
        
        if needs_quotes:
            env_lines.append(f'{key}="{value_str}"')
        else:
            env_lines.append(f'{key}={value_str}')
    
    # 寫入 .env 檔案
    with open('/var/app/staging/.env', 'w') as f:
        f.write('\n'.join(env_lines))
        f.write('\n')  # 結尾加上換行
    
    print(f'Successfully wrote {len(env_lines)} variables to .env', file=sys.stderr)
    
except json.JSONDecodeError as e:
    print(f'ERROR: Failed to parse JSON: {e}', file=sys.stderr)
    sys.exit(1)
except Exception as e:
    print(f'ERROR: {e}', file=sys.stderr)
    sys.exit(1)
PYTHON_SCRIPT

PYTHON_EXIT_CODE=$?

# 清理臨時檔案
rm -f $TEMP_JSON

# 檢查 Python 腳本執行結果
if [ $PYTHON_EXIT_CODE -ne 0 ]; then
    echo "ERROR: Failed to generate .env file"
    exit 1
fi

# === 驗證 .env 檔案 ===
echo ""
echo "=== Verifying .env file ==="

if [ ! -f "$ENV_FILE" ]; then
    echo "ERROR: .env file was not created"
    exit 1
fi

LINE_COUNT=$(wc -l < $ENV_FILE)
FILE_SIZE=$(stat -c%s "$ENV_FILE" 2>/dev/null || stat -f%z "$ENV_FILE")

echo "✓ .env file created successfully"
echo "  - Location: $ENV_FILE"
echo "  - Lines: $LINE_COUNT"
echo "  - Size: $FILE_SIZE bytes"

# 顯示前 10 個變數名稱（不含值）
if [ $LINE_COUNT -gt 0 ]; then
    echo ""
    echo "Environment variables (first 10):"
    head -10 $ENV_FILE | cut -d'=' -f1 | sed 's/^/  - /'
    
    if [ $LINE_COUNT -gt 10 ]; then
        echo "  ... and $((LINE_COUNT - 10)) more"
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