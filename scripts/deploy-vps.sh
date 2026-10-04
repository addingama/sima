#!/usr/bin/env sh
set -eu

DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
DEPLOY_REPO="${DEPLOY_REPO:-}"
DEPLOY_PATH="${DEPLOY_PATH:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
ENV_FILE="${ENV_FILE:-.env}"
DEPLOY_BACKUP="${DEPLOY_BACKUP:-true}"

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
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
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

ENV_FILE="$ENV_FILE" COMPOSE_FILE="$COMPOSE_FILE" sh scripts/check-production-env.sh

if [ "$DEPLOY_BACKUP" = "true" ] && compose ps --status running --services 2>/dev/null | grep -qx app; then
    echo "Membuat backup database sebelum deployment..."
    compose exec -T app php artisan sima:backup-db
fi

echo "Membangun image production..."
compose build --pull app worker frontend

echo "Menyiapkan MySQL dan Redis..."
compose up -d --wait mysql redis

echo "Menjalankan migration satu kali dengan image baru..."
compose run --rm app php artisan migrate --force --no-interaction

echo "Menjalankan seluruh layanan..."
compose up -d --wait --remove-orphans

health_timeout="${SIMA_HEALTH_TIMEOUT:-$(env_value SIMA_HEALTH_TIMEOUT)}"
health_timeout="${health_timeout:-120}"
elapsed=0
echo "Menunggu health check aplikasi (maksimal ${health_timeout}s)..."
while ! compose exec -T nginx wget -qO- http://127.0.0.1/api/health 2>/dev/null | grep -q '"status":"ok"'; do
    if [ "$elapsed" -ge "$health_timeout" ]; then
        echo "Health check gagal setelah ${health_timeout}s." >&2
        compose ps >&2
        compose logs --tail=100 app frontend nginx worker >&2
        exit 1
    fi

    sleep 5
    elapsed=$((elapsed + 5))
done

compose ps
echo "Deployment berhasil dan aplikasi sehat."
