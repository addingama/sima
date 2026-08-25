# Panduan Deployment SIMA

Panduan ini menjelaskan deployment produksi SIMA menggunakan Docker Compose. Untuk arsitektur sistem, lihat [ARCHITECTURE.md](./ARCHITECTURE.md).

## Prasyarat

- Docker Engine 24+ dan Docker Compose v2
- Server minimal: 2 vCPU, 4 GB RAM, 40 GB disk (sesuaikan volume transaksi/lampiran)
- Domain + TLS (disarankan reverse proxy eksternal atau certbot di depan NGINX)

## Quick start (single server)

### 1. Clone & konfigurasi

```bash
git clone https://github.com/YOUR_ORG/sima.git
cd sima
cp .env.production.example .env
```

Edit `.env`:

| Variabel | Wajib | Keterangan |
|----------|-------|------------|
| `APP_KEY` | Ya | Generate: `php artisan key:generate --show` |
| `APP_URL` | Ya | URL publik, mis. `https://sima.example.com` |
| `DB_PASSWORD` | Ya | Password user MySQL aplikasi |
| `MYSQL_ROOT_PASSWORD` | Ya | Password root MySQL |
| `REDIS_PASSWORD` | Disarankan | Password Redis |
| `SANCTUM_STATEFUL_DOMAINS` | Ya | Domain frontend (tanpa scheme) |

### 2. Build & jalankan

```bash
make prod-up
# atau:
docker compose -f docker-compose.prod.yml up -d --build
```

### 3. Seed awal (hanya pertama kali)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force
```

### 4. Verifikasi

```bash
curl -s http://localhost/api/health | jq
make health
```

Response sehat:

```json
{
  "data": {
    "status": "ok",
    "checks": {
      "database": "ok",
      "cache": "ok",
      "redis": "ok"
    }
  }
}
```

## Struktur layanan

| Service | Port internal | Keterangan |
|---------|---------------|------------|
| nginx | 80 (mapped `SIMA_HTTP_PORT`) | Entry point publik |
| app | 9000 (FPM) | Laravel API |
| frontend | 3000 | Next.js |
| mysql | 3306 | Database |
| redis | 6379 | Cache / queue / session |
| worker | — | Supervisor: queue + scheduler |

## TLS / HTTPS

Compose default hanya HTTP port 80. Opsi umum:

1. **Reverse proxy eksternal** (Traefik, Caddy, cloud LB) → forward ke `:80`
2. **Certbot standalone** di host, mount sertifikat ke NGINX (perlu konfigurasi SSL tambahan)

Setelah HTTPS aktif, pastikan:

```
APP_URL=https://sima.example.com
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=sima.example.com
```

## Storage, upload & backup

### Volume

- `sima-prod_app_storage` — seluruh `/var/www/html/storage`, termasuk `storage/app/private/attachments` untuk lampiran upload.
- `sima-prod_backup_data` — dump DB terkompresi (`storage/backups`).
- `sima-prod_mysql_data`, `sima-prod_redis_data` — data database dan Redis.

Lampiran upload memakai disk Laravel `local` dan disimpan di `storage/app/private/attachments/*`.
Pada production, path itu berada di named volume Docker `sima-prod_app_storage`, sehingga file tetap ada saat container di-rebuild atau image diganti. Jangan menjalankan `docker compose down -v` di production karena opsi `-v` menghapus named volume beserta file upload dan database.

### Backup otomatis

Scheduler menjalankan `sima:backup-db` setiap hari 01:30 WIB (lihat `routes/console.php`).

Manual:

```bash
make backup
# atau
docker compose -f docker-compose.prod.yml exec app php artisan sima:backup-db
```

### Restore

```bash
gunzip -c storage/backups/sima_YYYYMMDD_HHMMSS.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T mysql \
  mysql -u root -p"$MYSQL_ROOT_PASSWORD" sima
```

> **Penting:** uji restore secara berkala di lingkungan staging.

## Auto deploy ke VPS via SSH

Workflow `.github/workflows/deploy.yml` melakukan SSH ke VPS, mengambil kode terbaru dari git, lalu build image Docker langsung di server dengan `docker compose build --pull`. Workflow ini otomatis berjalan saat push ke branch `main` memakai environment GitHub `ahp-production`, dan bisa dijalankan manual dari **Actions → Deploy → Run workflow** untuk memilih `ahp-production` atau `ahp-staging`.

### Secrets GitHub

Isi secrets berikut pada environment GitHub `ahp-production` dan `ahp-staging` sesuai target server masing-masing.

| Secret | Keterangan |
|--------|------------|
| `DEPLOY_HOST` | IP/hostname server |
| `DEPLOY_USER` | User SSH |
| `DEPLOY_SSH_KEY` | Private key SSH |
| `DEPLOY_PATH` | Path repo di server, mis. `/opt/sima` untuk production atau `/opt/sima-staging` untuk staging |
| `DEPLOY_REPO` | Opsional. URL git yang dipakai VPS untuk clone/pull jika berbeda dari URL repo GitHub default |

### Setup pertama di VPS

Install Docker Engine, Docker Compose v2, dan Git di VPS. Pastikan user deploy bisa menjalankan Docker.

```bash
sudo mkdir -p /opt/sima
sudo chown "$USER":"$USER" /opt/sima
git clone git@github.com:addingama/sima.git /opt/sima
cd /opt/sima
cp .env.production.example .env
```

Isi `.env` produksi minimal:

| Variabel | Keterangan |
|----------|------------|
| `APP_KEY` | Generate dari mesin dev: `php artisan key:generate --show` |
| `APP_URL` | URL publik aplikasi |
| `DB_PASSWORD` | Password user MySQL aplikasi |
| `MYSQL_ROOT_PASSWORD` | Password root MySQL |
| `REDIS_PASSWORD` | Password Redis, disarankan |
| `SANCTUM_STATEFUL_DOMAINS` | Domain aplikasi tanpa scheme |

Deploy manual pertama:

```bash
DEPLOY_PATH=/opt/sima DEPLOY_BRANCH=main sh scripts/deploy-vps.sh
docker compose -f docker-compose.prod.yml exec app php artisan sima:create-admin
```

Perintah `sima:create-admin` membuat administrator produksi pertama (interaktif atau `--name`, `--email`, `--password`). **Jangan** mengandalkan `UserSeeder` / akun `*@sima.test` di produksi.

## Rolling update (zero/minimal downtime)

```bash
DEPLOY_PATH=/opt/sima DEPLOY_BRANCH=main sh scripts/deploy-vps.sh
```

Di balik layar, script menjalankan:

```bash
git fetch --prune origin main
git reset --hard origin/main
docker compose -f docker-compose.prod.yml build --pull app worker frontend
docker compose -f docker-compose.prod.yml up -d --remove-orphans
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
```

Perintah ini tidak menghapus named volume, sehingga `sima-prod_app_storage`, `sima-prod_mysql_data`, dan volume persisten lain tetap dipakai ulang.

## Monitoring

- Health: `GET /api/health` (503 jika DB/cache/Redis gagal)
- Log aplikasi: `docker compose -f docker-compose.prod.yml logs -f app worker`
- Log NGINX: `docker compose -f docker-compose.prod.yml logs -f nginx`

## Troubleshooting

| Gejala | Penyebab umum | Solusi |
|--------|---------------|--------|
| 502 Bad Gateway | PHP-FPM belum siap | Cek `docker compose logs app` |
| 401 pada frontend | Sanctum domain salah | Sesuaikan `SANCTUM_STATEFUL_DOMAINS` |
| Queue tidak jalan | Worker down | `docker compose logs worker` |
| Permission denied storage | Ownership volume | `chown -R www-data:www-data storage` di container |
| Migrate gagal | DB belum healthy | Tunggu mysql healthcheck, cek kredensial |

## Development vs production

| Aspek | Dev (`docker-compose.yml`) | Prod (`docker-compose.prod.yml`) |
|-------|---------------------------|----------------------------------|
| Kode | Volume mount live | Baked in image |
| Debug | `APP_DEBUG=true` | `APP_DEBUG=false` |
| Cache/Queue | Redis | Redis |
| Frontend | `npm run dev` terpisah | Container Next.js |
| Worker | Service terpisah queue + scheduler | Supervisor (satu container) |
