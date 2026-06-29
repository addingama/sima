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

## Storage & backup

### Volume

- `app_storage` — `storage/app` (lampiran, file private)
- `backup_data` — dump DB terkompresi (`storage/backups`)
- `mysql_data`, `redis_data` — data persisten

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

## Deploy dari GHCR (CI/CD)

Workflow `.github/workflows/deploy.yml` mem-build dan push image ke GitHub Container Registry.

### Secrets GitHub (environment `production`)

| Secret | Keterangan |
|--------|------------|
| `DEPLOY_HOST` | IP/hostname server |
| `DEPLOY_USER` | User SSH |
| `DEPLOY_SSH_KEY` | Private key SSH |
| `DEPLOY_PATH` | Path repo di server, mis. `/opt/sima` |

### Pull image di server

Tambahkan ke `.env` di server:

```env
SIMA_API_IMAGE=ghcr.io/YOUR_ORG/sima/sima-api:latest
SIMA_WORKER_IMAGE=ghcr.io/YOUR_ORG/sima/sima-worker:latest
SIMA_FRONTEND_IMAGE=ghcr.io/YOUR_ORG/sima/sima-frontend:latest
```

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

Trigger deploy manual: **Actions → Deploy → Run workflow**.

## Rolling update (zero/minimal downtime)

```bash
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --no-deps app worker frontend
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
```

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
