#!/usr/bin/env sh
# Menulis file environment server dari variabel yang sudah diekspor.
# Nilai tidak dicetak ke stdout.
set -eu

output="${1:-.env}"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

require() {
    key="$1"
    eval "value=\${$key-}"
    [ -n "$value" ] || fail "$key wajib diisi."
}

quote() {
    printf '%s' "$1" | sed 's/\\/\\\\/g; s/"/\\"/g'
}

write_line() {
    key="$1"
    eval "value=\${$key-}"
    printf '%s="%s"\n' "$key" "$(quote "$value")"
}

for key in APP_KEY APP_URL DB_PASSWORD MYSQL_ROOT_PASSWORD REDIS_PASSWORD SANCTUM_STATEFUL_DOMAINS; do
    require "$key"
done

: "${APP_NAME:=SIMA}"
: "${APP_ENV:=production}"
: "${APP_DEBUG:=false}"
: "${APP_TIMEZONE:=Asia/Jakarta}"
: "${APP_LOCALE:=id}"
: "${APP_FALLBACK_LOCALE:=en}"
: "${APP_MAINTENANCE_DRIVER:=file}"
: "${BCRYPT_ROUNDS:=12}"
: "${LOG_CHANNEL:=stack}"
: "${LOG_STACK:=json,daily}"
: "${LOG_LEVEL:=info}"
: "${LOG_DAILY_DAYS:=30}"
: "${DB_CONNECTION:=mysql}"
: "${DB_HOST:=mysql}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=sima}"
: "${DB_USERNAME:=sima}"
: "${SESSION_DRIVER:=redis}"
: "${SESSION_LIFETIME:=120}"
: "${SESSION_ENCRYPT:=true}"
: "${SESSION_DOMAIN:=}"
: "${SESSION_SECURE_COOKIE:=true}"
: "${BROADCAST_CONNECTION:=log}"
: "${FILESYSTEM_DISK:=local}"
: "${QUEUE_CONNECTION:=redis}"
: "${CACHE_STORE:=redis}"
: "${CACHE_PREFIX:=sima_prod_}"
: "${REDIS_CLIENT:=phpredis}"
: "${REDIS_HOST:=redis}"
: "${REDIS_PORT:=6379}"
: "${MAIL_MAILER:=log}"
: "${MAIL_HOST:=}"
: "${MAIL_PORT:=587}"
: "${MAIL_USERNAME:=}"
: "${MAIL_PASSWORD:=}"
: "${MAIL_ENCRYPTION:=tls}"
: "${MAIL_FROM_ADDRESS:=noreply@localhost}"
: "${MAIL_FROM_NAME:=SIMA}"
: "${FRONTEND_URL:=$APP_URL}"
: "${NEXT_PUBLIC_API_URL:=/api}"
: "${SANCTUM_TOKEN_EXPIRATION:=720}"
: "${AWS_ACCESS_KEY_ID:=}"
: "${AWS_SECRET_ACCESS_KEY:=}"
: "${AWS_DEFAULT_REGION:=}"
: "${AWS_BUCKET:=}"
: "${AWS_URL:=}"
: "${AWS_ENDPOINT:=}"
: "${AWS_USE_PATH_STYLE_ENDPOINT:=false}"
: "${SIMA_PORTAL_AUTO_CREATE_USER:=false}"
: "${SIMA_PORTAL_DEFAULT_PASSWORD:=}"
: "${RUN_MIGRATIONS:=false}"
: "${SIMA_HTTP_PORT:=80}"
: "${SIMA_FRONTEND_PORT:=3010}"
: "${SIMA_BACKUP_DIR:=storage/backups}"
: "${SIMA_HEALTH_TIMEOUT:=120}"
: "${SIMA_DOMAIN:=}"
: "${SIMA_TLS_EMAIL:=}"
: "${SIMA_HOST_STORAGE:=}"
: "${SIMA_HOST_BACKUP_DIR:=}"

umask 077
tmp="${output}.tmp"
: >"$tmp"

for key in \
    APP_NAME APP_ENV APP_KEY APP_DEBUG APP_TIMEZONE APP_URL \
    APP_LOCALE APP_FALLBACK_LOCALE APP_MAINTENANCE_DRIVER BCRYPT_ROUNDS \
    LOG_CHANNEL LOG_STACK LOG_LEVEL LOG_DAILY_DAYS \
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD MYSQL_ROOT_PASSWORD \
    SESSION_DRIVER SESSION_LIFETIME SESSION_ENCRYPT SESSION_DOMAIN SESSION_SECURE_COOKIE \
    BROADCAST_CONNECTION FILESYSTEM_DISK QUEUE_CONNECTION \
    CACHE_STORE CACHE_PREFIX \
    REDIS_CLIENT REDIS_HOST REDIS_PASSWORD REDIS_PORT \
    MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD MAIL_ENCRYPTION \
    MAIL_FROM_ADDRESS MAIL_FROM_NAME \
    FRONTEND_URL NEXT_PUBLIC_API_URL SANCTUM_STATEFUL_DOMAINS SANCTUM_TOKEN_EXPIRATION \
    AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION AWS_BUCKET AWS_URL AWS_ENDPOINT \
    AWS_USE_PATH_STYLE_ENDPOINT \
    SIMA_PORTAL_AUTO_CREATE_USER SIMA_PORTAL_DEFAULT_PASSWORD \
    RUN_MIGRATIONS SIMA_HTTP_PORT SIMA_FRONTEND_PORT SIMA_BACKUP_DIR SIMA_HEALTH_TIMEOUT SIMA_DOMAIN \
    SIMA_TLS_EMAIL SIMA_HOST_STORAGE SIMA_HOST_BACKUP_DIR
do
    write_line "$key" >>"$tmp"
done

mv "$tmp" "$output"
echo "File environment ditulis."
