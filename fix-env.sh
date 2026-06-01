#!/bin/bash
# Script to fix .env on server
# Upload this to server and run: bash fix-env.sh

cat > /www/wwwroot/absensi.ozodemo.my.id/.env << 'EOF'
APP_NAME="Tyas Photo"
APP_ENV=production
APP_KEY=base64:pzff/KxaP1OUxyMUWPV4Kf84LGSQDuEIqEi6A06FSD4=
APP_DEBUG=false
APP_URL=https://absensi.ozodemo.my.id
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi_ozodemo
DB_USERNAME=absensi_ozodemo
DB_PASSWORD=z85DenPCSKL82ERb

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database

CACHE_STORE=database

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="noreply@absensi.ozodemo.my.id"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

GOOGLE_SERVICE_ACCOUNT_FILE=/www/wwwroot/absensi.ozodemo.my.id/storage/google-service-account.json
EOF

echo "Done! .env has been fixed."
