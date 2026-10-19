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
| `REDIS_PASSWORD` | Ya | Password Redis |
| `SANCTUM_STATEFUL_DOMAINS` | Ya | Domain frontend (tanpa scheme) |
| `SESSION_SECURE_COOKIE` | Ya untuk HTTPS | Harus `true` pada production HTTPS |
| `SESSION_DOMAIN` | Sesuai topologi | Domain cookie, mis. `.sima.example.com` |
| `SIMA_PORTAL_AUTO_CREATE_USER` | Tidak | Default production `false`; aktifkan hanya bila memang diperlukan |
| `SIMA_PORTAL_DEFAULT_PASSWORD` | Jika auto-create aktif | Minimal 12 karakter dan bukan password demo |

Validasi konfigurasi sebelum build:

```bash
make prod-check
# atau: sh scripts/check-production-env.sh
```

Preflight menolak secret kosong/placeholder, `APP_KEY` non-Laravel, cookie tidak aman pada HTTPS, konfigurasi S3 yang tidak lengkap, dan Compose yang invalid. Nilai secret tidak dicetak.

### 2. Build & jalankan

Untuk setup/deploy di server gunakan script resmi:

```bash
DEPLOY_PATH=/opt/sima DEPLOY_BRANCH=main sh scripts/deploy-vps.sh
```

`make prod-up` tetap tersedia untuk smoke test lokal, tetapi tidak menggantikan preflight, backup, migration, dan health verification pada script deploy.

### 3. Buat admin awal (hanya pertama kali)

```bash
docker compose -f docker-compose.prod.yml exec app php artisan sima:create-admin
```

Jangan menjalankan `db:seed` di production karena `UserSeeder` berisi akun contoh `*@sima.test`.

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

Ada dua topologi:

| Aspek | Staging (`ahp-staging`) | Production |
|-------|-------------------------|------------|
| Nginx | Di host VPS | Container (`bundled-nginx`) |
| MySQL | Di host VPS | Container (`bundled-mysql`) |
| Cara menyalakan | `SIMA_NGINX=host`, `SIMA_MYSQL=host`, `DB_HOST=host.docker.internal` | `SIMA_NGINX=container` dan `SIMA_MYSQL=container` (default) |

Pada staging, PHP-FPM membuka `127.0.0.1:9000` dan Next.js membuka `127.0.0.1` pada port `SIMA_FRONTEND_PORT` (default `3010`; di dalam container Next tetap mendengar `3000`). Nginx host menerima port 80/443 dan meminta sertifikat Let's Encrypt. Aplikasi di container menghubungi MySQL host lewat `host.docker.internal`. MySQL hanya mendengarkan `127.0.0.1` dan alamat gateway Docker, lalu user aplikasi boleh masuk dari jaringan container.

`scripts/configure-host-nginx.sh` dan `scripts/configure-host-mysql.sh` menulis konfigurasi itu saat deploy. Production tidak menjalankan kedua script tersebut.

Staging tidak memakai sertifikat yang dibeli. Deploy memasang nginx HTTP dulu, lalu Certbot meminta sertifikat Let's Encrypt untuk `SIMA_DOMAIN` lewat `http://<domain>/.well-known/acme-challenge/`. Setelah file sertifikat ada, nginx beralih ke `deploy/nginx/sima-host.conf` (port 443, port 80 hanya untuk tantangan perpanjangan dan redirect HTTPS).

Perpanjangan berjalan sendiri. Certbot memperbarui sertifikat lewat `certbot.timer` (atau cron dua kali sehari bila timer tidak ada). Hook `/etc/letsencrypt/renewal-hooks/deploy/sima-reload-nginx.sh` me-reload nginx setelah perpanjangan berhasil. Isi variable GitHub `SIMA_TLS_EMAIL` supaya Let's Encrypt bisa mengirim peringatan bila perpanjangan gagal. DNS domain harus mengarah ke VPS, dan port 80 serta 443 harus terbuka.

Setelah HTTPS aktif, pastikan:

```
APP_URL=https://sima.ahpeduli.org
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=sima.ahpeduli.org
SIMA_DOMAIN=sima.ahpeduli.org
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

Workflow `.github/workflows/deploy.yml` masuk ke VPS lewat SSH **username dan password**, menulis `.env` dari secrets/variables environment GitHub, mengambil kode terbaru, lalu build image Docker di server. Push ke `main` memakai environment `ahp-staging`. Environment itu adalah **server staging** `https://sima.ahpeduli.org`. Deploy manual dari **Actions → Deploy → Run workflow** tetap bisa memilih `ahp-staging` atau `ahp-production`.

### Secrets dan variables GitHub

Isi pada environment GitHub target. Jangan menyimpan password di repository.

| Secret | Keterangan |
|--------|------------|
| `DEPLOY_HOST` | IP/hostname server |
| `DEPLOY_USER` | User SSH |
| `DEPLOY_PASSWORD` | Password SSH user tersebut |
| `DEPLOY_PATH` | Path repo di server, mis. `/opt/sima-ahp` untuk staging |
| `DEPLOY_REPO` | Opsional. URL git yang dipakai VPS untuk clone/pull |
| `APP_KEY` | Key Laravel, prefix `base64:` |
| `DB_PASSWORD` | Password user MySQL aplikasi |
| `MYSQL_ROOT_PASSWORD` | Password root MySQL |
| `REDIS_PASSWORD` | Password Redis |

Konfigurasi non-rahasia (URL, domain, mailer, port) disimpan sebagai **environment variables** dengan nama yang sama seperti kunci `.env`. Workflow merakit file itu lewat `scripts/render-deploy-env.sh` setiap deploy. Untuk staging, variable `SIMA_NGINX` dan `SIMA_MYSQL` bernilai `host`, `DB_HOST` bernilai `host.docker.internal`, `COMPOSE_FILES` bernilai `docker-compose.prod.yml docker-compose.staging.yml`, dan `SIMA_DOMAIN` bernilai `sima.ahpeduli.org`.

### Setup pertama di VPS

Install Docker Engine, Docker Compose v2.24+, Git, Nginx, dan MySQL 8 di VPS staging. User SSH harus boleh menjalankan Docker, dan login password harus aktif (`PasswordAuthentication yes`). Nginx dan MySQL staging adalah layanan sistem, bukan service Compose.

Workflow deploy membuat direktori, menulis `.env`, lalu menjalankan stack. Path staging saat ini: `/opt/sima-ahp`.

Deploy manual di server, bila `.env` sudah ada:

```bash
DEPLOY_PATH=/opt/sima-ahp DEPLOY_BRANCH=main \
  SIMA_NGINX=host \
  SIMA_MYSQL=host \
  COMPOSE_FILES="docker-compose.prod.yml docker-compose.staging.yml" \
  sh scripts/deploy-vps.sh
docker compose -f docker-compose.prod.yml -f docker-compose.staging.yml exec app php artisan sima:create-admin
```

Perintah `sima:create-admin` membuat administrator produksi pertama (interaktif atau `--name`, `--email`, `--password`) dan mengisi peran dari `RolePermissionSeeder` bila tabel peran masih kosong. Deploy juga menjalankan `RolePermissionSeeder` dan `SystemFundSeeder` setelah migrasi. **Jangan** menjalankan `db:seed` penuh: `UserSeeder` membuat akun `*@sima.test`.

## Rolling update (zero/minimal downtime)

```bash
DEPLOY_PATH=/opt/sima DEPLOY_BRANCH=main sh scripts/deploy-vps.sh
```

Di balik layar, script menjalankan:

```bash
git fetch --prune origin main
git reset --hard origin/main
sh scripts/check-production-env.sh
php artisan sima:backup-db # bila stack lama sedang berjalan
docker compose -f docker-compose.prod.yml build --pull app worker frontend
docker compose -f docker-compose.prod.yml up -d --wait mysql redis
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.prod.yml up -d --wait --remove-orphans
# Poll GET /api/health; gagal deploy bila tidak sehat dalam batas waktu.
```

Perintah ini tidak menghapus named volume, sehingga `sima-prod_app_storage`, `sima-prod_mysql_data`, dan volume persisten lain tetap dipakai ulang.

Opsi script:

| Variabel | Default | Keterangan |
|----------|---------|------------|
| `DEPLOY_PATH` | wajib | Path repository pada VPS |
| `DEPLOY_BRANCH` | `main` | Branch yang dideploy |
| `DEPLOY_REPO` | kosong | Wajib hanya untuk clone pertama |
| `ENV_FILE` | `.env` | File environment Compose |
| `COMPOSE_FILE` | `docker-compose.prod.yml` | File Compose bila `COMPOSE_FILES` kosong |
| `COMPOSE_FILES` | sama dengan `COMPOSE_FILE` | Daftar file Compose, dipisah spasi. Staging menambah `docker-compose.staging.yml` |
| `SIMA_NGINX` | `container` | `container` menyalakan nginx Compose (production). `host` memakai nginx di VPS (staging) |
| `SIMA_MYSQL` | `container` | `container` menyalakan MySQL Compose (production). `host` memakai MySQL di VPS (staging); `DB_HOST` harus `host.docker.internal` |
| `DEPLOY_BACKUP` | `true` | Backup DB sebelum update jika app lama sedang berjalan |
| `SIMA_HEALTH_TIMEOUT` | `120` | Batas tunggu health check dalam detik |
| `SIMA_FRONTEND_PORT` | `3010` | Port localhost VPS untuk Next.js staging. Variable GitHub dengan nama yang sama |

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
