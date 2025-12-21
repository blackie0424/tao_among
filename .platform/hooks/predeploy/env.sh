#!/bin/bash

set -e

# 獲取部署目錄
if [ -d "/var/app/staging" ]; then
    APP_DIR="/var/app/staging"
else
    APP_DIR="/var/app/current"
fi

ENV_FILE="$APP_DIR/.env"

# 從 EB 環境變數創建 .env 文件
echo "Creating .env file from Elastic Beanstalk environment variables..."

/opt/elasticbeanstalk/bin/get-config environment | jq -r 'to_entries[] | "\(.key)=\(.value)"' > $ENV_FILE

# 設定權限
chown webapp:webapp $ENV_FILE
chmod 644 $ENV_FILE

echo ".env file created successfully"