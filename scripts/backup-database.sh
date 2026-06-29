#!/usr/bin/env bash
# Backup manual SIMA (MySQL) — wrapper untuk cron/ops di luar Laravel.
# Usage: ./scripts/backup-database.sh [output_dir]
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

OUTPUT_DIR="${1:-${SIMA_BACKUP_DIR:-storage/backups}}"
mkdir -p "$OUTPUT_DIR"

php artisan sima:backup-db --path="$OUTPUT_DIR"

echo "Selesai. Simpan salinan off-site (S3/NAS) sesuai kebijakan retensi lembaga."
