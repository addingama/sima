#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT HUP INT TERM

export DB_DATABASE="sima"
export DB_USERNAME="sima"
export DB_PASSWORD="test-db-password"
export SIMA_DOCKER_GATEWAY="172.17.0.1"
export SIMA_MYSQL_ROOT="$TMP_DIR/system"

sh "$ROOT/scripts/configure-host-mysql.sh"

conf="$TMP_DIR/system/mysql/sima-bind.cnf"
sql="$TMP_DIR/system/mysql/init.sql"
grep -q "bind-address = 127.0.0.1,172.17.0.1" "$conf"
grep -q "CREATE DATABASE IF NOT EXISTS \`sima\`" "$sql"
grep -q "IDENTIFIED BY 'test-db-password'" "$sql"
grep -q "GRANT ALL PRIVILEGES ON \`sima\`.* TO 'sima'@'%'" "$sql"

export DB_PASSWORD="pa'ss"
sh "$ROOT/scripts/configure-host-mysql.sh" >/dev/null
grep -q "IDENTIFIED BY 'pa''ss'" "$sql"

unset DB_PASSWORD
if sh "$ROOT/scripts/configure-host-mysql.sh" >"$TMP_DIR/missing.out" 2>&1; then
    echo "Konfigurasi MySQL host seharusnya gagal tanpa DB_PASSWORD." >&2
    exit 1
fi
grep -q "DB_PASSWORD wajib diisi" "$TMP_DIR/missing.out"

echo "Test MySQL host staging lulus."
