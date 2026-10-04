#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM

mkdir -p "$TMP_DIR/bin"

cat >"$TMP_DIR/bin/docker" <<'EOF'
#!/usr/bin/env sh
if [ "$1" = "compose" ] && [ "${2:-}" = "version" ]; then
    exit 0
fi

if [ "$1" = "compose" ]; then
    exit 0
fi

exit 1
EOF
chmod +x "$TMP_DIR/bin/docker"

write_valid_env() {
    cat >"$TMP_DIR/.env" <<'EOF'
APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=
APP_URL=https://sima.example.com
DB_PASSWORD=test-db-password
MYSQL_ROOT_PASSWORD=test-root-password
SANCTUM_STATEFUL_DOMAINS=sima.example.com
REDIS_PASSWORD=test-redis-password
SESSION_SECURE_COOKIE=true
FILESYSTEM_DISK=local
SIMA_PORTAL_AUTO_CREATE_USER=false
EOF
}

run_check() {
    PATH="$TMP_DIR/bin:$PATH" \
        ENV_FILE="$TMP_DIR/.env" \
        COMPOSE_FILE="$ROOT/docker-compose.prod.yml" \
        sh "$ROOT/scripts/check-production-env.sh"
}

write_valid_env
run_check >/dev/null

sed -i.bak 's/test-db-password/CHANGE_ME_STRONG_PASSWORD/' "$TMP_DIR/.env"
if run_check >"$TMP_DIR/output" 2>&1; then
    echo "Preflight seharusnya menolak placeholder secret." >&2
    exit 1
fi
grep -q 'DB_PASSWORD masih memakai placeholder' "$TMP_DIR/output"

write_valid_env
sed -i.bak 's/SESSION_SECURE_COOKIE=true/SESSION_SECURE_COOKIE=false/' "$TMP_DIR/.env"
if run_check >"$TMP_DIR/output" 2>&1; then
    echo "Preflight seharusnya menolak cookie tidak aman pada HTTPS." >&2
    exit 1
fi
grep -q 'SESSION_SECURE_COOKIE harus true' "$TMP_DIR/output"

write_valid_env
sed -i.bak 's/FILESYSTEM_DISK=local/FILESYSTEM_DISK=s3/' "$TMP_DIR/.env"
if run_check >"$TMP_DIR/output" 2>&1; then
    echo "Preflight seharusnya menolak konfigurasi S3 tidak lengkap." >&2
    exit 1
fi
grep -q 'AWS_ACCESS_KEY_ID wajib diisi' "$TMP_DIR/output"

write_valid_env
sed -i.bak 's/SIMA_PORTAL_AUTO_CREATE_USER=false/SIMA_PORTAL_AUTO_CREATE_USER=true/' "$TMP_DIR/.env"
if run_check >"$TMP_DIR/output" 2>&1; then
    echo "Preflight seharusnya menolak auto-create portal tanpa password kuat." >&2
    exit 1
fi
grep -q 'SIMA_PORTAL_DEFAULT_PASSWORD minimal 12 karakter' "$TMP_DIR/output"

echo "Test preflight production lulus."
