#!/usr/bin/env sh
# Menyiapkan MySQL di host untuk staging.
# Production memakai service mysql di Compose (profile bundled-mysql), bukan script ini.
#
# SIMA_MYSQL_ROOT mengalihkan path sistem ke direktori uji.
set -eu

database="${DB_DATABASE:-sima}"
username="${DB_USERNAME:-sima}"
password="${DB_PASSWORD:-}"
root="${SIMA_MYSQL_ROOT:-}"
gateway="${SIMA_DOCKER_GATEWAY:-}"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

[ -n "$password" ] || fail "DB_PASSWORD wajib diisi."

case "$database" in
    *[!A-Za-z0-9_]*) fail "DB_DATABASE hanya boleh huruf, angka, dan garis bawah." ;;
esac

case "$username" in
    *[!A-Za-z0-9_]*) fail "DB_USERNAME hanya boleh huruf, angka, dan garis bawah." ;;
esac

if [ -z "$gateway" ] && [ -z "$root" ] && command -v docker >/dev/null 2>&1; then
    gateway="$(docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}' 2>/dev/null || true)"
fi
gateway="${gateway:-172.17.0.1}"

sql_escape() {
    printf '%s' "$1" | sed "s/'/''/g"
}

escaped_password="$(sql_escape "$password")"

if [ -n "$root" ]; then
    conf_dir="$root/mysql"
    mkdir -p "$conf_dir"
    conf_path="$conf_dir/sima-bind.cnf"
    sql_path="$conf_dir/init.sql"
else
    sql_path="$(mktemp)"
    if [ -d /etc/mysql/mysql.conf.d ]; then
        conf_path="/etc/mysql/mysql.conf.d/sima-bind.cnf"
    elif [ -d /etc/mysql/mariadb.conf.d ]; then
        conf_path="/etc/mysql/mariadb.conf.d/sima-bind.cnf"
    else
        mkdir -p /etc/mysql/conf.d
        conf_path="/etc/mysql/conf.d/sima-bind.cnf"
    fi
fi

cat >"$conf_path" <<EOF
[mysqld]
bind-address = 127.0.0.1,${gateway}
EOF

cat >"$sql_path" <<EOF
CREATE DATABASE IF NOT EXISTS \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${username}'@'%' IDENTIFIED BY '${escaped_password}';
ALTER USER '${username}'@'%' IDENTIFIED BY '${escaped_password}';
GRANT ALL PRIVILEGES ON \`${database}\`.* TO '${username}'@'%';
FLUSH PRIVILEGES;
EOF

if [ -n "$root" ]; then
    echo "Konfigurasi MySQL host ditulis ke $conf_path"
    exit 0
fi

if ! command -v mysql >/dev/null 2>&1; then
    DEBIAN_FRONTEND=noninteractive apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y mysql-server
fi

if command -v systemctl >/dev/null 2>&1; then
    systemctl enable mysql || systemctl enable mariadb || true
    systemctl restart mysql || systemctl restart mariadb
fi

ready=0
attempt=0
while [ "$attempt" -lt 30 ]; do
    if mysqladmin --protocol=socket ping >/dev/null 2>&1; then
        ready=1
        break
    fi
    attempt=$((attempt + 1))
    sleep 1
done

[ "$ready" -eq 1 ] || fail "MySQL host tidak menjawab."

mysql --protocol=socket -u root <"$sql_path"
rm -f "$sql_path"
echo "Database ${database} siap di MySQL host."
