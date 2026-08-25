#!/usr/bin/env sh
set -eu

DEPLOY_BRANCH="${DEPLOY_BRANCH:-main}"
DEPLOY_REPO="${DEPLOY_REPO:-}"
DEPLOY_PATH="${DEPLOY_PATH:-}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"

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

if [ ! -f .env ]; then
    echo "File .env produksi belum ada di $DEPLOY_PATH." >&2
    echo "Buat dari .env.production.example dan isi secret produksi sebelum deploy." >&2
    exit 1
fi

docker compose -f "$COMPOSE_FILE" config >/dev/null

docker compose -f "$COMPOSE_FILE" build --pull app worker frontend
docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force --no-interaction
docker compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache
docker compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache
docker compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache

docker compose -f "$COMPOSE_FILE" ps
