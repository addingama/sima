#!/bin/sh
set -e

URL="${SIMA_HEALTH_URL:-http://nginx/api/health}"
curl -fsS "$URL" | grep -q '"status":"ok"'
