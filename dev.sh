#!/usr/bin/env bash
# Menjalankan development server SIMA: Laravel API + Next.js.
# Usage: ./dev.sh
#
# Port (bisa diubah lewat env):
#   API_PORT=8000  WEB_PORT=3000  ./dev.sh
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

API_PORT="${API_PORT:-8000}"
WEB_PORT="${WEB_PORT:-3000}"

if [[ ! -f artisan ]]; then
  echo "Jalankan skrip ini dari root repo SIMA." >&2
  exit 1
fi

if [[ ! -d vendor ]]; then
  echo "Backend belum siap. Jalankan: composer install" >&2
  exit 1
fi

if [[ ! -d frontend/node_modules ]]; then
  echo "Frontend belum siap. Jalankan: (cd frontend && npm install)" >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  echo "File .env belum ada. Jalankan: cp .env.example .env && php artisan key:generate" >&2
  exit 1
fi

if [[ ! -f frontend/.env && -f frontend/.env.example ]]; then
  cp frontend/.env.example frontend/.env
  echo "Membuat frontend/.env dari frontend/.env.example"
fi

backend_pid=""
frontend_pid=""

cleanup() {
  trap - INT TERM EXIT
  if [[ -n "${backend_pid}" ]]; then
    kill "${backend_pid}" 2>/dev/null || true
  fi
  if [[ -n "${frontend_pid}" ]]; then
    kill "${frontend_pid}" 2>/dev/null || true
  fi
  wait 2>/dev/null || true
}

trap cleanup INT TERM EXIT

echo "API  http://127.0.0.1:${API_PORT}/api"
echo "Web  http://127.0.0.1:${WEB_PORT}"
echo "Ctrl+C untuk menghentikan keduanya."
echo ""

php artisan serve --host=127.0.0.1 --port="${API_PORT}" &
backend_pid=$!

npm --prefix frontend run dev -- --hostname 127.0.0.1 --port "${WEB_PORT}" &
frontend_pid=$!

wait
