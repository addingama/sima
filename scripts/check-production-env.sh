#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$ROOT"

ENV_FILE="${ENV_FILE:-.env}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

env_value() {
    key="$1"
    awk -F= -v key="$key" '
        $0 !~ /^[[:space:]]*#/ && $1 == key {
            value = substr($0, index($0, "=") + 1)
            sub(/^[[:space:]]+/, "", value)
            sub(/[[:space:]]+$/, "", value)
            if ((value ~ /^".*"$/) || (value ~ /^\047.*\047$/)) {
                value = substr(value, 2, length(value) - 2)
            }
            print value
            exit
        }
    ' "$ENV_FILE"
}

command -v docker >/dev/null 2>&1 || fail "Docker tidak ditemukan."
docker compose version >/dev/null 2>&1 || fail "Docker Compose v2 tidak tersedia."
[ -f "$ENV_FILE" ] || fail "File environment tidak ditemukan: $ENV_FILE"
[ -f "$COMPOSE_FILE" ] || fail "Compose production tidak ditemukan: $COMPOSE_FILE"

for key in APP_KEY APP_URL DB_PASSWORD MYSQL_ROOT_PASSWORD REDIS_PASSWORD SANCTUM_STATEFUL_DOMAINS; do
    value="$(env_value "$key")"
    [ -n "$value" ] || fail "$key wajib diisi di $ENV_FILE."
    case "$value" in
        *CHANGE_ME*|*change_me*) fail "$key masih memakai placeholder." ;;
    esac
done

app_key="$(env_value APP_KEY)"
case "$app_key" in
    base64:*) ;;
    *) fail "APP_KEY harus berupa key Laravel (prefix base64:)." ;;
esac

app_url="$(env_value APP_URL)"
secure_cookie="$(env_value SESSION_SECURE_COOKIE)"
case "$app_url" in
    https://*)
        [ "$secure_cookie" = "true" ] || fail "SESSION_SECURE_COOKIE harus true untuk APP_URL HTTPS."
        ;;
esac

filesystem_disk="$(env_value FILESYSTEM_DISK)"
if [ "$filesystem_disk" = "s3" ]; then
    for key in AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_DEFAULT_REGION AWS_BUCKET; do
        [ -n "$(env_value "$key")" ] || fail "$key wajib diisi ketika FILESYSTEM_DISK=s3."
    done
fi

portal_auto_create="$(env_value SIMA_PORTAL_AUTO_CREATE_USER)"
if [ "$portal_auto_create" = "true" ]; then
    portal_password="$(env_value SIMA_PORTAL_DEFAULT_PASSWORD)"
    [ "${#portal_password}" -ge 12 ] || fail "SIMA_PORTAL_DEFAULT_PASSWORD minimal 12 karakter ketika auto-create portal aktif."
    [ "$portal_password" != "password" ] || fail "SIMA_PORTAL_DEFAULT_PASSWORD tidak boleh memakai password demo."
fi

docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" config --quiet

echo "Preflight production berhasil: environment dan Compose valid."
