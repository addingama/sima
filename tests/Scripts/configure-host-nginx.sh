#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM

export SIMA_DOMAIN="sima.ahpeduli.org"
export SIMA_FRONTEND_PORT="3456"
export SIMA_HOST_STORAGE="$TMP_DIR/storage"
export SIMA_HOST_BACKUP_DIR="$TMP_DIR/backups"
export SIMA_NGINX_ROOT="$TMP_DIR/system"

sh "$ROOT/scripts/configure-host-nginx.sh" prepare
[ -d "$TMP_DIR/storage/framework/cache/data" ]
[ -d "$TMP_DIR/backups" ]

sh "$ROOT/scripts/configure-host-nginx.sh" install
conf="$TMP_DIR/system/nginx/sima.conf"
grep -q "server_name sima.ahpeduli.org;" "$conf"
grep -q "fastcgi_pass 127.0.0.1:9000;" "$conf"
grep -q "proxy_pass http://127.0.0.1:3456;" "$conf"
grep -q "alias $TMP_DIR/storage/app/public/;" "$conf"
if grep -q "ssl_certificate" "$conf"; then
    echo "Tanpa sertifikat, konfigurasi host harus tetap HTTP." >&2
    exit 1
fi
if grep -q "frontend:3000" "$conf"; then
    echo "Nginx host tidak boleh mengarah ke hostname container." >&2
    exit 1
fi

mkdir -p "$TMP_DIR/system/letsencrypt/live/sima.ahpeduli.org"
touch "$TMP_DIR/system/letsencrypt/live/sima.ahpeduli.org/fullchain.pem"
sh "$ROOT/scripts/configure-host-nginx.sh" install
grep -q "ssl_certificate /etc/letsencrypt/live/sima.ahpeduli.org/fullchain.pem;" "$conf"
grep -q "fastcgi_param HTTPS on;" "$conf"
grep -q 'return 301 https://$host$request_uri;' "$conf"

hook="$TMP_DIR/system/letsencrypt/renewal-hooks/deploy/sima-reload-nginx.sh"
[ -x "$hook" ]
grep -q "systemctl reload nginx" "$hook"

echo "Test nginx host staging lulus."
