# SIMA — Sistem Informasi Manajemen Amanah

> **File ini berisi aturan permanen project SIMA.**
> Sebelum menulis kode baru, **cek aturan di file ini terlebih dahulu**.
> Jika ada konflik antara prompt dan file ini, **ikuti file ini** kecuali diinstruksikan eksplisit oleh pemilik project.

---

## Core Concept: Amanah Ledger

SIMA mengelola **dana titipan / dana amanah** untuk lembaga sosial. Inti sistem adalah **Amanah Ledger**: satu buku besar immutable yang menjadi **sumber kebenaran tunggal**.

### Prinsip utama (WAJIB)

- Sistem mengelola dana titipan/dana amanah.
- Setiap **uang masuk** harus memiliki tujuan/niat/alokasi.
- Setiap **uang keluar** harus mengambil dari Dana Amanah yang sesuai.
- **Saldo kas/bank** = saldo fisik (di mana uang berada).
- **Saldo Dana Amanah** = saldo pembatas penggunaan (untuk apa uang boleh dipakai).
- Saldo kas/bank dan total Dana Amanah **harus bisa direkonsiliasi** (selisih = 0).
- **Tidak boleh ada hard delete** untuk transaksi finansial.
- Transaksi yang sudah **approved tidak boleh diedit langsung**.
- Koreksi **hanya** melalui **void/reversal**.
- Semua perubahan penting **harus masuk audit trail**.

### Model ledger (Amanah Ledger — double-entry)

Setiap transaksi finansial menghasilkan baris `ledger_entries` (debit/credit):

- `transaction_type` + `transaction_id` → sumber transaksi
- `ledger_account_type` + `ledger_account_id` → akun buku besar (`account` = kas/bank, `fund` = Dana Amanah)
- Saldo **tidak** disimpan statis; dihitung dari agregasi ledger
- Total debit = total credit (jurnal seimbang); total kas/bank = total Dana Amanah

Aliran uang (pasangan debit/credit per nominal):

```
Penerimaan (approve)   :  Akun + , Dana Tujuan +   (1 leg per alokasi; alokasi inline saat draft)
Pengeluaran (approve)  :  Akun − , Dana Tujuan −   (1 leg per sumber dana)
Biaya Bank (post)      :  Akun − , Dana (admin) −
Reversal               :  negasi seluruh leg transaksi sumber
```

Catatan: alokasi penerimaan **menyatu** dengan penerimaan (bukan modul terpisah via Dana SUSPENSE).
Dana `SYS-SUSPENSE` tetap ada untuk kebutuhan sistem/legacy; alur aktif tidak memposting ke suspense.

---

## Tech Stack

**Backend**
- Laravel 11
- MySQL 8 (dev memakai MariaDB yang kompatibel)
- Laravel Sanctum (auth API token)
- Spatie Permission (RBAC)
- Spatie Activity Log (audit) — lihat **Status Implementasi** di bawah

**Frontend** (belum dibuat — tunggu instruksi)
- Next.js + TypeScript
- Tailwind CSS
- Shadcn Admin Dashboard (template utama)
- shadcn/ui
- TanStack Table
- React Hook Form
- Zod

---

## Backend Rules (WAJIB)

- Gunakan **Service Layer** untuk business logic (lihat `app/Services`).
- Gunakan **Form Request** untuk validasi input.
- Gunakan **API Resource** untuk shape response.
- Gunakan **Policy/Permission** untuk authorization (middleware `permission:*`).
- Gunakan **database transaction** untuk semua proses finansial (`DB::transaction`).
- Gunakan **decimal(18,2)** untuk semua nominal uang.
- **JANGAN** gunakan float untuk uang. Gunakan `bcmath` (`bcadd`, `bcsub`, `bccomp`, `bcmul`) untuk operasi.
- **JANGAN** simpan saldo hanya sebagai angka statis tanpa ledger.
- Saldo **harus bisa dihitung dari `ledger_entries`**.
- Cache saldo boleh dibuat **hanya** sebagai optimisasi, **bukan** source of truth.
- `ledger_entries` bersifat **append-only/immutable** (trigger DB + guard model). Pembatalan = reversal.

---

## Core Modules

- Donatur
- Vendor
- Rekening Kas/Bank
- Dana Amanah
- Event/Program
- Penerimaan
- Alokasi Penerimaan
- Pengeluaran
- Sumber Dana Pengeluaran (`expense_fund_sources`) — satu pengeluaran bisa dari banyak Dana Amanah
- Approval
- Biaya Administrasi Bank
- Reversal/Void
- Rekonsiliasi
- Audit Trail
- Laporan
- Portal Donatur

### Status transaksi

- Penerimaan: `draft → submitted → approved(=post ke ledger) → reversed` (atau `rejected`)
- Alokasi: disimpan inline dengan penerimaan; diposting bersamaan saat penerimaan di-approve
- Pengeluaran: `draft → submitted → verified → approved(=post ke ledger) → reversed` (atau `rejected`)
- Biaya Bank: `draft → posted/deferred → reversed`

### Role

`admin`, `bendahara`, `verifikator`, `ketua`, `auditor`, `donatur`.
Daftar permission & pemetaan role ada di `config/sima.php`.

---

## UI Rules (JANGAN dikerjakan dulu)

- **Jangan buat UI sampai backend core selesai** dan pemilik project meminta.
- UI **wajib** memakai **Shadcn Admin Dashboard** sebagai template utama.
- **Jangan** membuat layout dashboard dari nol.
- Ikuti struktur folder template.
- Gunakan komponen **shadcn/ui**.
- Gunakan **table-first layout** untuk data keuangan (TanStack Table).
- UX harus mudah untuk **bendahara non-teknis**.

---

## Development Rule

1. Sebelum menulis kode baru, **baca file ini**.
2. Jika prompt bertentangan dengan file ini, **ikuti file ini** kecuali ada instruksi eksplisit.
3. Setiap perubahan finansial: bungkus dengan transaction, posting via ledger, jaga invariant non-negatif, catat ke audit & approval bila relevan.
4. Setiap transaksi punya **nomor unik** (lihat `DocumentNumberService`: `RCP/DSB/FEE/LIB`).

---

## Status Implementasi (per Jun 2026)

> Bagian ini menjaga sinkronisasi antara aturan & kode nyata. Perbarui saat ada perubahan.

**Sudah ada (backend):**
- Migration: master data, transaksi finansial, `ledger_entries` (double-entry Amanah Ledger),
  `idempotency_keys`, `audit_logs`, dll.
- Service layer: `LedgerService` (mesin inti), `TrustFundBalanceService`, `ReceiptService`, `ExpenseService`,
  `BankFeeService`, `ReversalService`, `ReconciliationService`, `OperationalLiabilityService`,
  `ApprovalService`, `AuditLogService`, `IdempotencyService`, `DocumentNumberService`.
- API + RBAC + Policy record-level + Form Request/Resource (modul finansial & master data).
- Saldo materialized + locking (P0), idempotency claim (race-safe), CI/tests, Docker.

**Catatan:**
- **Audit**: master data memakai owen-it; aksi workflow finansial memakai `AuditLogService` + `ApprovalService`.
- **Modul Vendor**: belum ada — tambahkan saat dibutuhkan.
- **User management API**: permission `user.manage` ada, endpoint belum dibuat.

**Belum dibuat:** seluruh frontend (sesuai UI Rules).
