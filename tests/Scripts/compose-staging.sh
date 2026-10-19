#!/usr/bin/env sh
# Staging harus bisa di-config tanpa service mysql, dan production tetap memakai named volume.
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose tidak tersedia." >&2
    exit 1
fi

write_env() {
    cat >"$TMP_DIR/.env" <<'EOF'
APP_KEY=base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=
APP_URL=https://sima.ahpeduli.org
DB_PASSWORD=test-db-password
MYSQL_ROOT_PASSWORD=test-root-password
REDIS_PASSWORD=test-redis-password
SANCTUM_STATEFUL_DOMAINS=sima.ahpeduli.org
SESSION_SECURE_COOKIE=true
DB_HOST=host.docker.internal
SIMA_DOMAIN=sima.ahpeduli.org
EOF
}

assert_no_mysql() {
    label="$1"
    shift
    if docker compose --env-file "$TMP_DIR/.env" "$@" config --services | grep -qx mysql; then
        echo "$label masih menyertakan service mysql." >&2
        exit 1
    fi
}

write_env
printf '\nSIMA_HOST_STORAGE=/var/lib/sima/storage\nSIMA_HOST_BACKUP_DIR=/var/lib/sima/backups\nSIMA_FRONTEND_PORT=3456\n' >>"$TMP_DIR/.env"

assert_no_mysql "Staging" -f "$ROOT/docker-compose.prod.yml" -f "$ROOT/docker-compose.staging.yml"
staging_config="$(docker compose --env-file "$TMP_DIR/.env" -f "$ROOT/docker-compose.prod.yml" -f "$ROOT/docker-compose.staging.yml" config)"
printf '%s\n' "$staging_config" | grep -q '/var/lib/sima/storage' || {
    echo "Staging harus memasang storage host ke container." >&2
    exit 1
}
printf '%s\n' "$staging_config" | grep -q 'published: "3456"' || {
    echo "Staging harus memetakan Next.js ke SIMA_FRONTEND_PORT." >&2
    exit 1
}

write_env
assert_no_mysql "Production tanpa profile MySQL" -f "$ROOT/docker-compose.prod.yml"

COMPOSE_PROFILES=bundled-nginx,bundled-mysql \
    docker compose --env-file "$TMP_DIR/.env" -f "$ROOT/docker-compose.prod.yml" config --services | grep -qx mysql
if COMPOSE_PROFILES=bundled-nginx,bundled-mysql \
    docker compose --env-file "$TMP_DIR/.env" -f "$ROOT/docker-compose.prod.yml" config | grep -q '/var/lib/sima/storage'; then
    echo "Production harus memakai named volume bila path host kosong." >&2
    exit 1
fi

echo "Test Compose staging lulus."
