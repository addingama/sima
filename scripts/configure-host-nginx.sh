#!/usr/bin/env sh
# Menyiapkan nginx di host untuk staging.
# Production memakai service nginx di Compose (profile bundled-nginx), bukan script ini.
#
# SIMA_NGINX_ROOT mengalihkan path sistem ke direktori uji.
set -eu

action="${1:-all}"
domain="${SIMA_DOMAIN:-}"
storage="${SIMA_HOST_STORAGE:-/var/lib/sima/storage}"
backup="${SIMA_HOST_BACKUP_DIR:-/var/lib/sima/backups}"
root="${SIMA_NGINX_ROOT:-}"

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

[ -n "$domain" ] || fail "SIMA_DOMAIN wajib diisi untuk nginx host."

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
repo_root="$(CDPATH= cd -- "$script_dir/.." && pwd)"

if [ -n "$root" ]; then
    cert_file="$root/letsencrypt/live/$domain/fullchain.pem"
    conf_dir="$root/nginx"
    mkdir -p "$conf_dir"
    conf_path="$conf_dir/sima.conf"
else
    cert_file="/etc/letsencrypt/live/$domain/fullchain.pem"
    if [ -d /etc/nginx/sites-available ]; then
        conf_dir="/etc/nginx/sites-available"
        conf_path="$conf_dir/sima.conf"
    else
        conf_dir="/etc/nginx/conf.d"
        conf_path="$conf_dir/sima.conf"
    fi
fi

prepare_storage() {
    mkdir -p \
        "$storage/app/public" \
        "$storage/app/private" \
        "$storage/framework/cache/data" \
        "$storage/framework/sessions" \
        "$storage/framework/views" \
        "$storage/logs" \
        "$backup"

    if [ -z "$root" ]; then
        mkdir -p /var/www/certbot
    fi

    if [ "$(id -u)" -eq 0 ]; then
        chown -R 82:82 "$storage" "$backup" || true
    fi
}

render_conf() {
    if [ -f "$cert_file" ]; then
        template="$repo_root/deploy/nginx/sima-host.conf"
    else
        template="$repo_root/deploy/nginx/sima-host-http.conf"
    fi

    mkdir -p "$conf_dir"
    sed \
        -e "s#__SIMA_DOMAIN__#$domain#g" \
        -e "s#__SIMA_STORAGE__#$storage#g" \
        "$template" >"$conf_path"
    echo "Konfigurasi nginx host ditulis ke $conf_path"
}

reload_nginx() {
    if [ -n "$root" ]; then
        return 0
    fi

    if ! command -v nginx >/dev/null 2>&1; then
        apt-get update
        apt-get install -y nginx
    fi

    mkdir -p "$conf_dir"
    if [ -d /etc/nginx/sites-enabled ] && [ -d /etc/nginx/sites-available ]; then
        ln -sfn "$conf_path" /etc/nginx/sites-enabled/sima.conf
        rm -f /etc/nginx/sites-enabled/default
    fi

    nginx -t
    if command -v systemctl >/dev/null 2>&1; then
        systemctl enable nginx
        systemctl reload nginx || systemctl restart nginx
    else
        nginx -s reload || nginx
    fi
}

issue_certificate() {
    if [ -n "$root" ] || [ -f "$cert_file" ]; then
        return 0
    fi

    if ! command -v certbot >/dev/null 2>&1; then
        apt-get update
        apt-get install -y certbot
    fi

    mkdir -p /var/www/certbot
    if [ -n "${SIMA_TLS_EMAIL:-}" ]; then
        certbot certonly --webroot -w /var/www/certbot -d "$domain" \
            --non-interactive --agree-tos -m "$SIMA_TLS_EMAIL"
    else
        certbot certonly --webroot -w /var/www/certbot -d "$domain" \
            --non-interactive --agree-tos --register-unsafely-without-email
    fi
}

install_renewal() {
    if [ -n "$root" ]; then
        hook_dir="$root/letsencrypt/renewal-hooks/deploy"
    else
        hook_dir="/etc/letsencrypt/renewal-hooks/deploy"
    fi

    mkdir -p "$hook_dir"
    cat >"$hook_dir/sima-reload-nginx.sh" <<'EOF'
#!/bin/sh
systemctl reload nginx
EOF
    chmod 755 "$hook_dir/sima-reload-nginx.sh"

    if [ -n "$root" ]; then
        return 0
    fi

    if command -v systemctl >/dev/null 2>&1 && systemctl cat certbot.timer >/dev/null 2>&1; then
        systemctl enable --now certbot.timer
        return 0
    fi

    cat >/etc/cron.d/sima-certbot <<'EOF'
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
0 3,15 * * * root certbot renew --quiet
EOF
}

case "$action" in
    prepare)
        prepare_storage
        ;;
    install)
        render_conf
        reload_nginx
        issue_certificate
        install_renewal
        render_conf
        reload_nginx
        ;;
    all)
        prepare_storage
        render_conf
        reload_nginx
        issue_certificate
        install_renewal
        render_conf
        reload_nginx
        ;;
    *)
        fail "Perintah tidak dikenal: $action"
        ;;
esac
