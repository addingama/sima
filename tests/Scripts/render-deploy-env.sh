#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM

export APP_KEY="base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY="
export APP_URL="https://sima.ahpeduli.org"
export DB_PASSWORD="test-db-password"
export MYSQL_ROOT_PASSWORD="test-root-password"
export REDIS_PASSWORD="test-redis-password"
export SANCTUM_STATEFUL_DOMAINS="sima.ahpeduli.org"
export SIMA_DOMAIN="sima.ahpeduli.org"
export APP_NAME="SIMA Dev"

stdout="$(sh "$ROOT/scripts/render-deploy-env.sh" "$TMP_DIR/.env")"
printf '%s\n' "$stdout" | grep -q "File environment ditulis."
printf '%s\n' "$stdout" | grep -q "test-db-password" && {
    echo "Nilai secret tidak boleh tercetak." >&2
    exit 1
}

grep -q 'APP_URL="https://sima.ahpeduli.org"' "$TMP_DIR/.env"
grep -q 'DB_PASSWORD="test-db-password"' "$TMP_DIR/.env"
grep -q 'SIMA_DOMAIN="sima.ahpeduli.org"' "$TMP_DIR/.env"
grep -q 'APP_NAME="SIMA Dev"' "$TMP_DIR/.env"

unset APP_KEY
if sh "$ROOT/scripts/render-deploy-env.sh" "$TMP_DIR/missing.env" >"$TMP_DIR/missing.out" 2>&1; then
    echo "Render seharusnya gagal bila APP_KEY kosong." >&2
    exit 1
fi
grep -q "APP_KEY wajib diisi" "$TMP_DIR/missing.out"

echo "Test render environment deploy lulus."
