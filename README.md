# SIMA — Sistem Informasi Manajemen Amanah

Backend untuk lembaga sosial yang mencatat dan mengelola **dana titipan/dana amanah**, memastikan setiap uang yang masuk hanya dapat dikeluarkan sesuai niat awal donatur.

Stack: **Laravel 11**, **MySQL/MariaDB**, **Laravel Sanctum** (auth API token), RBAC via **spatie/laravel-permission**, audit trail via **owen-it/laravel-auditing**.

> Fokus repo tahap ini: desain database yang benar & aman untuk audit + API backend. **Belum** ada UI.

---

## Konsep Inti

| Konsep | Penjelasan |
|---|---|
| **Kas/Bank** (`accounts`) | Tempat uang fisik berada (kas tunai / rekening bank). |
| **Dana Amanah** (`funds`) | Batasan/peruntukan penggunaan uang (restricted/unrestricted). |
| **Ledger** (`ledger_entries`) | **Sumber kebenaran tunggal**, append-only/immutable. Setiap baris mencatat pergerakan uang pada **dua dimensi**: `account_id` (di kas mana) dan `fund_id` (peruntukan apa). |

### Mengapa satu ledger dua dimensi?

Setiap entri ledger membawa `amount` bertanda (+ masuk / − keluar) yang berlaku sekaligus untuk **akun** dan **dana**. Dengan ini:

- **Saldo Kas/Bank** = `SUM(amount)` per `account_id`
- **Saldo Dana Amanah** = `SUM(amount)` per `fund_id`

Tidak ada kolom saldo yang disimpan terpisah → tidak ada risiko saldo "drift". Saldo selalu dihitung dari ledger.

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

Semua model master & transaksi meng-implement `OwenIt\Auditing\Contracts\Auditable`. Perubahan (create/update/delete) otomatis tercatat di tabel `audits` beserta user, nilai lama, dan nilai baru. Riwayat workflow approval juga dicatat di tabel `approvals` (polymorphic).

---

## Struktur Domain

### Tabel utama

- Master: `donors`, `funds`, `accounts`, `programs`
- Transaksi: `receipts`, `receipt_allocations`, `disbursements`, `bank_fees`
- Inti: `ledger_entries`
- Pendukung: `approvals`, `bank_reconciliations`, `bank_reconciliation_lines`, `document_sequences`
- Sistem: `users`, `roles`, `permissions`, `audits`, `personal_access_tokens`

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

### Pengeluaran (workflow)
- `GET|POST /api/disbursements`, `GET|PUT /api/disbursements/{id}`
- `POST /api/disbursements/{id}/submit`
- `POST /api/disbursements/{id}/verify`
- `POST /api/disbursements/{id}/approve` ← posting ke ledger
- `POST /api/disbursements/{id}/reject` (`{ reason }`)
- `POST /api/disbursements/{id}/reverse` (`{ reason }`)

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
- `GET /api/reports/fund-balances` · `account-balances` · `ledger` · `fund-statement`
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

---

## Catatan keamanan

- Audit Composer sempat memblokir beberapa advisory Laravel 11; untuk scaffolding ini diabaikan di `composer.json` (`audit.ignore`). **Tinjau & perbarui** sebelum produksi.
- User DB & password di README hanya untuk pengembangan lokal — ganti untuk produksi.
- Saldo awal kas/bank diposting sebagai ledger entry bertipe `opening` (lawan dana sistem `opening_equity`).
