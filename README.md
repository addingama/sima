# SIMA — Sistem Informasi Manajemen Amanah

Backend + frontend untuk lembaga sosial yang mencatat dan mengelola **dana titipan/dana amanah**, memastikan setiap uang yang masuk hanya dapat dikeluarkan sesuai niat awal donatur.

| Stack | Teknologi |
|-------|-----------|
| Backend | Laravel 11, PHP 8.2, MySQL 8, Redis |
| Frontend | Next.js, TypeScript, shadcn/ui, TanStack Table |
| Auth | Laravel Sanctum + RBAC (Spatie Permission) |
| Audit | owen-it/laravel-auditing |

## Dokumentasi operasional

| Dokumen | Isi |
|---------|-----|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Diagram arsitektur & aliran data |
| [docs/DANA-AMANAH.md](docs/DANA-AMANAH.md) | Tipe Dana Amanah: restricted vs unrestricted |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Deploy produksi (Docker, TLS, backup) |
| [docs/CODING_STANDARDS.md](docs/CODING_STANDARDS.md) | Standar kode backend & frontend |
| [docs/CONTRIBUTING.md](docs/CONTRIBUTING.md) | Alur kontribusi & PR checklist |

## Quick start

### Development (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

- API: http://localhost:8080/api  
- Frontend: `cd frontend && npm install && npm run dev` → http://localhost:3000

### Production

```bash
cp .env.production.example .env
# edit APP_KEY, passwords, domain
make prod-up
curl -s http://localhost/api/health | jq
```

Detail lengkap: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

### Makefile

```bash
make dev-up      # stack development
make prod-up     # stack production
make test        # PHPUnit
make backup      # sima:backup-db
make health      # GET /api/health
```

---

## Konsep Inti

| Konsep | Penjelasan |
|---|---|
| **Kas/Bank** (`accounts`) | Tempat uang fisik berada (kas tunai / rekening bank). |
| **Dana Amanah** (`funds`) | Batasan/peruntukan penggunaan uang. Tipe: **`restricted`** (terikat niat donatur) vs **`unrestricted`** (dana umum). Detail: [docs/DANA-AMANAH.md](docs/DANA-AMANAH.md). |
| **Ledger** (`ledger_entries`) | **Sumber kebenaran tunggal**, append-only/immutable. Setiap baris mencatat pergerakan uang pada **dua dimensi**: `account_id` (di kas mana) dan `fund_id` (peruntukan apa). |

### Mengapa satu ledger dua dimensi?

Setiap entri ledger membawa `amount` bertanda (+ masuk / − keluar) yang berlaku sekaligus untuk **akun** dan **dana**. Dengan ini:

- **Saldo Kas/Bank** = `SUM(amount)` per `account_id`
- **Saldo Dana Amanah** = `SUM(amount)` per `fund_id`

Tidak ada kolom saldo yang disimpan terpisah → tidak ada risiko saldo "drift". Saldo selalu dihitung dari ledger.

### Tipe Dana Amanah (restricted vs unrestricted)

| Tipe | Arti singkat |
|------|--------------|
| **Restricted** | Dana **terikat peruntukan/niat donatur** (mis. Zakat, Infaq program tertentu). Default saat buat master baru. |
| **Unrestricted** | Dana **umum/bebas** lembaga; lebih fleksibel untuk operasional. Semua dana sistem (Suspense, Operasional, dll.) bertipe ini. |

Perbedaan teknis penting: **biaya bank tidak boleh dibebankan ke dana restricted** — sistem otomatis menolak; gunakan Dana Operasional (unrestricted). Pengeluaran biasa boleh memakai kedua tipe selama saldo dana cukup.

Penjelasan lengkap, contoh, dan referensi kode: **[docs/DANA-AMANAH.md](docs/DANA-AMANAH.md)**.

### Invariant yang dijaga

1. Saldo setiap akun **tidak boleh negatif**.
2. Saldo setiap dana **tidak boleh negatif**.
3. Pengeluaran ditolak bila saldo Dana Amanah **atau** saldo akun tidak mencukupi.

`LedgerService::post()` memposting semua "leg" dalam satu transaksi DB, lalu memverifikasi ulang seluruh saldo akun & dana yang terdampak. Bila ada yang negatif → **rollback** + exception.

### Aliran uang

```
Penerimaan (post)      :  Akun + , Dana SUSPENSE +
Alokasi (post)         :  Dana SUSPENSE − , Dana Tujuan +   (akun tetap)
Pengeluaran (approve)  :  Akun − , Dana Tujuan −
Biaya Bank (post)      :  Akun − , Dana (admin) −
```

Penerimaan masuk dulu ke **Dana Sistem "Suspense"** (penampung sementara). Modul **Alokasi** lalu memindahkannya ke satu/beberapa Dana Amanah. Sisa yang belum dialokasikan tetap di suspense.

### Tidak ada hapus — hanya reversal

- Transaksi tidak pernah dihapus. Pembatalan dilakukan via **reversal**: entri ledger baru bertipe `reversal` yang menegasi entri asli (`reversal_of_id`), dan status transaksi menjadi `reversed`.
- Reversal tetap tunduk pada invariant. Contoh: penerimaan tidak bisa di-reverse bila dananya sudah dialokasikan/dipakai (saldo akan negatif → ditolak). Karena itu alokasi harus di-reverse lebih dulu.

### Hardening immutability ledger

- **Level DB**: trigger `BEFORE UPDATE` & `BEFORE DELETE` pada `ledger_entries` menolak operasi (MySQL/MariaDB).
- **Level model**: `LedgerEntry` melempar exception pada `updating`/`deleting`.

### Audit trail

Semua model master & transaksi meng-implement `OwenIt\Auditing\Contracts\Auditable`. Perubahan (create/update/delete) otomatis tercatat di tabel `audit_logs` beserta user, nilai lama, dan nilai baru. Riwayat workflow approval juga dicatat di tabel `approvals` (polymorphic).

> Catatan: audit owen-it dinonaktifkan untuk eksekusi CLI (`config/audit.php` → `console => false`). Pencatatan audit aktif pada jalur HTTP/aplikasi.

---

## Struktur Domain

### Tabel utama

- Master: `donors`, `funds`, `accounts`, `programs`
- Transaksi: `receipts`, `receipt_allocations`, `disbursements`, `expense_fund_sources`, `bank_fees`
- Inti: `ledger_entries`
- Pendukung: `approvals`, `attachments`, `operational_liabilities`, `bank_reconciliations`, `bank_reconciliation_lines`, `document_sequences`
- Sistem: `users`, `roles`, `permissions`, `audit_logs`, `personal_access_tokens`

> **Satu pengeluaran, banyak Dana Amanah.** `disbursements` tidak lagi memiliki `fund_id` tunggal. Sumber dana disimpan di `expense_fund_sources` (`fund_id`, `program_id?`, `amount`). Saat di-approve, **satu leg ledger dibuat per sumber dana**, sehingga setiap Dana Amanah berkurang sesuai porsinya dan total = `disbursements.amount`.

> **Lampiran/bukti** (`attachments`) bersifat polymorphic — dapat ditautkan ke penerimaan, pengeluaran, biaya bank, dan liabilitas.

> **Liabilitas operasional** (`operational_liabilities`) adalah register komitmen/utang (gaji, sewa, tagihan). Penyelesaiannya menautkan satu Pengeluaran yang sudah di-approve (tanpa double-count kas).

### Service layer (`app/Services`)

- `LedgerService` — posting, hitung saldo, reversal, penjagaan invariant.
- `ReceiptService`, `AllocationService`, `DisbursementService`, `BankFeeService` — orkestrasi status + posting ledger.
- `DocumentNumberService` — generator nomor dokumen (`RCP/2026/000001`, dst.) aman race-condition.

### Status transaksi

- **Penerimaan**: `draft → posted → reversed`
- **Alokasi**: `posted → reversed`
- **Pengeluaran**: `draft → submitted → verified → approved(=post) → reversed` (atau `rejected`)
- **Biaya Bank**: `draft → posted → reversed`

---

## Role & Permission

| Role | Ringkasan akses |
|---|---|
| **Admin** | Seluruh permission (super admin). |
| **Bendahara** | Kelola master (donatur/program), buat & post penerimaan, alokasi, buat & submit pengeluaran, biaya bank, rekonsiliasi. |
| **Verifikator** | Verifikasi/tolak pengeluaran, lihat data. |
| **Ketua** | Setujui (approve)/tolak pengeluaran, lihat data & laporan. |
| **Auditor** | Read-only seluruh data + audit trail + laporan. |
| **Donatur** | Portal donatur (hanya data miliknya). |

Daftar lengkap permission & pemetaan role ada di `config/sima.php`. Diterapkan via middleware route `permission:...`.

---

## Setup

### Prasyarat
PHP 8.3, Composer, MySQL/MariaDB, (Node 20 untuk frontend Next.js — terpisah).

### Langkah

```bash
composer install
cp .env.example .env   # bila perlu
php artisan key:generate
```

Sesuaikan `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sima
DB_USERNAME=sima
DB_PASSWORD=sima_secret

FRONTEND_URL=http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=localhost:3000,127.0.0.1:3000
```

Buat database & user (contoh):

```sql
CREATE DATABASE sima CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sima'@'%' IDENTIFIED BY 'sima_secret';
GRANT ALL PRIVILEGES ON sima.* TO 'sima'@'%';
FLUSH PRIVILEGES;
```

Migrasi & seed:

```bash
php artisan migrate --seed
php artisan serve
```

### Akun contoh (password: `password`)

| Email | Role |
|---|---|
| admin@sima.test | Admin |
| bendahara@sima.test | Bendahara |
| verifikator@sima.test | Verifikator |
| ketua@sima.test | Ketua |
| auditor@sima.test | Auditor |

---

## API

Base URL: `/api`. Auth: **Bearer token** (Sanctum).

### Autentikasi
- `POST /api/login` → `{ token, user }`
- `GET /api/me`
- `POST /api/logout`

### Master
- `GET|POST /api/donors`, `GET|PUT|DELETE /api/donors/{id}`
- `GET|POST /api/funds`, `GET|PUT|DELETE /api/funds/{id}` (saldo disertakan)
- `GET|POST /api/accounts`, `GET|PUT|DELETE /api/accounts/{id}` (saldo disertakan)
- `GET|POST /api/programs`, `GET|PUT|DELETE /api/programs/{id}`

### Penerimaan & Alokasi
- `GET|POST /api/receipts`, `GET /api/receipts/{id}`
- `POST /api/receipts/{id}/post`
- `POST /api/receipts/{id}/reverse` (`{ reason }`)
- `GET|POST /api/receipts/{id}/allocations`
- `POST /api/allocations/{id}/reverse` (`{ reason }`)

### Pengeluaran (workflow, multi-dana)
- `GET|POST /api/disbursements`, `GET|PUT /api/disbursements/{id}`
- `POST /api/disbursements/{id}/submit`
- `POST /api/disbursements/{id}/verify`
- `POST /api/disbursements/{id}/approve` ← posting ke ledger (satu leg per sumber dana)
- `POST /api/disbursements/{id}/reject` (`{ reason }`)
- `POST /api/disbursements/{id}/reverse` (`{ reason }`)

Body `store` menyertakan `sources` (total harus = `amount`):

```json
{
  "disbursement_date": "2026-06-29",
  "account_id": 1,
  "amount": 500000,
  "payee": "Vendor",
  "sources": [
    { "fund_id": 4, "amount": 300000 },
    { "fund_id": 5, "amount": 200000 }
  ]
}
```

### Liabilitas Operasional
- `GET|POST /api/liabilities`, `GET|PUT /api/liabilities/{id}`
- `POST /api/liabilities/{id}/settle` (`{ disbursement_id }`)
- `POST /api/liabilities/{id}/void` (`{ reason }`)

### Lampiran / Bukti
- `GET /api/attachments?attachable_type=&attachable_id=`
- `POST /api/attachments` (multipart: `attachable_type`, `attachable_id`, `file`, `title?`)
  - `attachable_type` ∈ `receipt|disbursement|bank_fee|liability`
- `GET /api/attachments/{id}/download`
- `DELETE /api/attachments/{id}`

### Biaya Bank
- `GET|POST /api/bank-fees`, `GET /api/bank-fees/{id}`
- `POST /api/bank-fees/{id}/post`
- `POST /api/bank-fees/{id}/reverse`

### Rekonsiliasi Bank
- `GET|POST /api/bank-reconciliations`, `GET /api/bank-reconciliations/{id}`
- `POST /api/bank-reconciliations/{id}/lines`
- `POST /api/bank-reconciliations/{id}/complete`

### Audit, Laporan, Dashboard, Portal
- `GET /api/audits`, `GET /api/audits/{id}`
- `GET /api/reports/fund-balances` · `account-balances` · `reconciliation-summary` · `ledger` · `fund-statement`
  - `reconciliation-summary`: memastikan total saldo kas/bank = total saldo Dana Amanah (`selisih` harus 0).
- `GET /api/dashboard`
- `GET /api/portal/profile` · `summary` · `donations` (role Donatur)

### Contoh

```bash
# Login
curl -X POST http://127.0.0.1:8000/api/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"bendahara@sima.test","password":"password"}'

# Buat penerimaan
curl -X POST http://127.0.0.1:8000/api/receipts \
  -H "Authorization: Bearer <TOKEN>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"receipt_date":"2026-06-29","account_id":1,"channel":"transfer","amount":1000000}'
```

Error domain & saldo dikembalikan sebagai **HTTP 422** dengan body `{ message, error }`.

### Health check

```bash
GET /api/health
```

Tanpa auth. Mengecek database, cache, dan Redis (jika dipakai). HTTP 503 bila degraded.

---

## Infrastruktur produksi

| Komponen | File / lokasi |
|----------|----------------|
| Docker multi-stage | `Dockerfile` (targets: `dev`, `production`, `worker`) |
| Frontend image | `frontend/Dockerfile` |
| Compose dev | `docker-compose.yml` |
| Compose prod | `docker-compose.prod.yml` |
| NGINX dev / prod | `docker/nginx/default.conf`, `docker/nginx/production.conf` |
| Supervisor (queue + scheduler) | `docker/supervisor/supervisord.conf` |
| Entrypoint | `docker/scripts/entrypoint.sh` |
| Env produksi | `.env.production.example` |
| CI | `.github/workflows/ci.yml` |
| Deploy | `.github/workflows/deploy.yml` |
| Backup terjadwal | `.github/workflows/backup.yml` + `sima:backup-db` |
| Scheduler | `routes/console.php` |

---

## Catatan keamanan

- Audit Composer sempat memblokir beberapa advisory Laravel 11; untuk scaffolding ini diabaikan di `composer.json` (`audit.ignore`). **Tinjau & perbarui** sebelum produksi.
- User DB & password di README hanya untuk pengembangan lokal — ganti untuk produksi.
- Saldo awal kas/bank diposting sebagai ledger entry bertipe `opening` (lawan dana sistem `opening_equity`).
