#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
CONF="$ROOT/docker/nginx/production.conf"

grep -q 'map $http_x_forwarded_proto $forwarded_proto' "$CONF"
grep -q 'proxy_set_header X-Forwarded-Proto $forwarded_proto;' "$CONF"
grep -q 'fastcgi_param HTTPS $fastcgi_https;' "$CONF"
grep -q 'fastcgi_param HTTP_X_FORWARDED_PROTO $forwarded_proto;' "$CONF"

if grep -q 'proxy_set_header X-Forwarded-Proto $scheme;' "$CONF"; then
    echo "Nginx tidak boleh menimpa proto TLS dengan scheme koneksi internal." >&2
    exit 1
fi

echo "Test header proto Nginx lulus."
