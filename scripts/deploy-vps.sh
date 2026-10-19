#!/usr/bin/env sh
set -eu

DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
DEPLOY_REPO="${DEPLOY_REPO:-}"
DEPLOY_PATH="${DEPLOY_PATH:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
COMPOSE_FILES="${COMPOSE_FILES:-$COMPOSE_FILE}"
ENV_FILE="${ENV_FILE:-.env}"
DEPLOY_BACKUP="${DEPLOY_BACKUP:-true}"
SIMA_NGINX="${SIMA_NGINX:-container}"
SIMA_MYSQL="${SIMA_MYSQL:-container}"

case "$SIMA_NGINX" in
    container|host) ;;
    *)
        echo "SIMA_NGINX harus container atau host." >&2
        exit 1
        ;;
esac

case "$SIMA_MYSQL" in
    container|host) ;;
    *)
        echo "SIMA_MYSQL harus container atau host." >&2
        exit 1
        ;;
esac

if [ -z "${COMPOSE_PROFILES+x}" ]; then
    profiles=""
    if [ "$SIMA_NGINX" = "container" ]; then
        profiles="bundled-nginx"
    fi
    if [ "$SIMA_MYSQL" = "container" ]; then
        if [ -n "$profiles" ]; then
            profiles="$profiles,bundled-mysql"
        else
            profiles="bundled-mysql"
        fi
    fi
    if [ -n "$profiles" ]; then
        COMPOSE_PROFILES="$profiles"
        export COMPOSE_PROFILES
    else
        unset COMPOSE_PROFILES || true
    fi
fi

if [ -z "$DEPLOY_PATH" ]; then
    echo "DEPLOY_PATH wajib diisi, contoh: /opt/sima" >&2
    exit 1
fi

if [ -z "$DEPLOY_REPO" ] && [ ! -d "$DEPLOY_PATH/.git" ]; then
    echo "DEPLOY_REPO wajib diisi untuk clone pertama kali." >&2
    exit 1
fi

mkdir -p "$(dirname "$DEPLOY_PATH")"

if [ ! -d "$DEPLOY_PATH/.git" ]; then
    git clone --branch "$DEPLOY_BRANCH" "$DEPLOY_REPO" "$DEPLOY_PATH"
fi

cd "$DEPLOY_PATH"

git fetch --prune origin "$DEPLOY_BRANCH"
git reset --hard "origin/$DEPLOY_BRANCH"

if [ ! -f "$ENV_FILE" ]; then
    echo "File environment produksi belum ada di $DEPLOY_PATH/$ENV_FILE." >&2
    echo "Buat dari .env.production.example dan isi secret produksi sebelum deploy." >&2
    exit 1
fi

compose() {
    files=""
    for file in $COMPOSE_FILES; do
        files="$files -f $file"
    done
    # shellcheck disable=SC2086
    docker compose --env-file "$ENV_FILE" $files "$@"
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

ENV_FILE="$ENV_FILE" COMPOSE_FILE="$COMPOSE_FILE" COMPOSE_FILES="$COMPOSE_FILES" sh scripts/check-production-env.sh

if [ "$DEPLOY_BACKUP" = "true" ] && compose ps --status running --services 2>/dev/null | grep -qx app; then
    echo "Membuat backup database sebelum deployment..."
    compose exec -T app php artisan sima:backup-db
fi

echo "Membangun image production..."
compose build --pull app worker frontend

if [ "$SIMA_MYSQL" = "host" ]; then
    db_host="$(env_value DB_HOST)"
    if [ -z "$db_host" ] || [ "$db_host" = "mysql" ]; then
        echo "DB_HOST harus host.docker.internal ketika SIMA_MYSQL=host." >&2
        exit 1
    fi
    echo "Menyiapkan Redis..."
    compose up -d --wait redis
    echo "Menyiapkan MySQL di host..."
    DB_DATABASE="$(env_value DB_DATABASE)" \
        DB_USERNAME="$(env_value DB_USERNAME)" \
        DB_PASSWORD="$(env_value DB_PASSWORD)" \
        sh scripts/configure-host-mysql.sh
else
    echo "Menyiapkan MySQL dan Redis..."
    compose up -d --wait mysql redis
fi

if [ "$SIMA_NGINX" = "host" ]; then
    echo "Menyiapkan direktori storage untuk nginx host..."
    SIMA_DOMAIN="$(env_value SIMA_DOMAIN)" \
        SIMA_HOST_STORAGE="$(env_value SIMA_HOST_STORAGE)" \
        SIMA_HOST_BACKUP_DIR="$(env_value SIMA_HOST_BACKUP_DIR)" \
        sh scripts/configure-host-nginx.sh prepare
fi

echo "Menjalankan migration satu kali dengan image baru..."
compose run --rm app php artisan migrate --force --no-interaction

echo "Menjalankan seluruh layanan..."
compose up -d --wait --remove-orphans

if [ "$SIMA_NGINX" = "host" ]; then
    echo "Memasang konfigurasi nginx di host..."
    SIMA_DOMAIN="$(env_value SIMA_DOMAIN)" \
        SIMA_HOST_STORAGE="$(env_value SIMA_HOST_STORAGE)" \
        SIMA_HOST_BACKUP_DIR="$(env_value SIMA_HOST_BACKUP_DIR)" \
        SIMA_TLS_EMAIL="$(env_value SIMA_TLS_EMAIL)" \
        sh scripts/configure-host-nginx.sh install
fi

health_ok() {
    if [ "$SIMA_NGINX" = "host" ]; then
        domain="$(env_value SIMA_DOMAIN)"
        url="https://${domain}/api/health"
        if command -v curl >/dev/null 2>&1; then
            curl -fsS "$url" 2>/dev/null | grep -q '"status":"ok"' && return 0
            return 1
        fi

        wget -qO- "$url" 2>/dev/null | grep -q '"status":"ok"' && return 0
        return 1
    fi

    compose exec -T nginx wget -qO- http://127.0.0.1/api/health 2>/dev/null | grep -q '"status":"ok"' && return 0
    return 1
}

health_timeout="${SIMA_HEALTH_TIMEOUT:-$(env_value SIMA_HEALTH_TIMEOUT)}"
health_timeout="${health_timeout:-120}"
elapsed=0
echo "Menunggu health check aplikasi (maksimal ${health_timeout}s)..."
while ! health_ok; do
    if [ "$elapsed" -ge "$health_timeout" ]; then
        echo "Health check gagal setelah ${health_timeout}s." >&2
        compose ps >&2
        if [ "$SIMA_NGINX" = "host" ]; then
            compose logs --tail=100 app frontend worker >&2 || true
        else
            compose logs --tail=100 app frontend nginx worker >&2 || true
        fi
        exit 1
    fi

    sleep 5
    elapsed=$((elapsed + 5))
done

compose ps
echo "Deployment berhasil dan aplikasi sehat."
