# Dana Amanah — Tipe & Peruntukan

Dokumen ini menjelaskan konsep **Dana Amanah** (`funds`) di SIMA, khususnya perbedaan tipe **restricted** dan **unrestricted**, serta bagaimana keduanya dipakai dalam alur keuangan.

## Apa itu Dana Amanah?

Dana Amanah bukan rekening bank fisik. Dana Amanah adalah **pembatas penggunaan**: menentukan *untuk apa* uang yang ada di kas/bank boleh dipakai.

| Dimensi | Arti | Contoh |
|---------|------|--------|
| **Kas/Bank** (`accounts`) | Di *mana* uang berada secara fisik | Kas kantor, rekening BCA |
| **Dana Amanah** (`funds`) | *Peruntukan* penggunaan uang | Zakat, Infaq Anak Yatim, Operasional |

Setiap baris `ledger_entries` selalu mencatat **dua dimensi sekaligus** (akun + dana). Saldo dana dihitung dari ledger, bukan disimpan sebagai angka statis di tabel `funds`.

---

## Tipe Dana Amanah

SIMA menyimpan tipe dana pada kolom `funds.type`. Untuk dana yang dibuat bendahara/admin, hanya ada **dua pilihan**:

| Tipe | Istilah | Makna |
|------|---------|-------|
| **`restricted`** | Terikat / restricted | Dana dengan **peruntukan atau niat donatur yang spesifik**. Uang hanya boleh dipakai sesuai tujuan tersebut. |
| **`unrestricted`** | Umum / unrestricted | Dana **umum atau bebas peruntukan** lembaga. Tidak terikat niat donatur pada satu program/tujuan tertentu. |

Definisi ini tercantum di migration `create_funds_table`:

- `restricted` = terikat niat donatur
- `unrestricted` = dana umum/bebas

Default saat membuat Dana Amanah baru di UI: **`restricted`**.

### Contoh praktis

| Nama Dana | Tipe | Kapan dipakai |
|-----------|------|---------------|
| Zakat Mustahik | `restricted` | Donasi zakat; pengeluaran harus sesuai syariat/per program zakat |
| Infaq Beasiswa Anak Yatim | `restricted` | Donasi dengan niat khusus beasiswa |
| Dana Sosial Umum | `unrestricted` | Donasi tanpa peruntukan ketat; fleksibel untuk kebutuhan lembaga |
| Dana Operasional (sistem) | `unrestricted` | Beban internal lembaga (mis. biaya bank) |

> **Catatan:** SIMA tidak otomatis memvalidasi *apakah* pengeluaran sesuai niat donatur pada level program/kategori. Tipe `restricted` menandai **klasifikasi akuntansi dan pembatasan teknis** (lihat aturan di bawah). Kepatuhan peruntukan donatur tetap tanggung jawab kebijakan & prosedur lembaga.

---

## Perbedaan Restricted vs Unrestricted

### 1. Maksud organisasi

| | Restricted | Unrestricted |
|---|-----------|--------------|
| **Sumber tipikal** | Donasi dengan niat jelas (zakat, infaq program X, wakaf) | Donasi umum, iuran operasional, dana cadangan |
| **Fleksibilitas** | Rendah — terikat peruntukan | Lebih tinggi — untuk kebutuhan umum lembaga |
| **Pelaporan** | Biasanya dilaporkan per program/tujuan donatur | Biasanya dilaporkan sebagai dana umum |

### 2. Aliran uang di SIMA

Keduanya mengikuti alur yang sama:

```
Penerimaan (post)  →  uang masuk ke akun + dana (awalnya suspense / alokasi inline)
Alokasi            →  pindah saldo antar dana (mis. suspense → dana tujuan)
Pengeluaran        →  kurangi akun + kurangi dana sumber (via expense_fund_sources)
```

Perbedaan tipe **tidak mengubah mekanisme ledger**; yang berubah adalah **pilihan dana mana** yang boleh dipakai pada transaksi tertentu.

### 3. Aturan sistem (hard rule di kode)

| Transaksi | Restricted | Unrestricted |
|-----------|------------|--------------|
| **Pengeluaran** | ✅ Boleh, jika dipilih sebagai sumber dana dan saldo cukup | ✅ Boleh, jika saldo cukup |
| **Biaya bank** | ❌ **Ditolak** — validator `BankFeeValidator` melarang | ✅ Boleh (default: **Dana Operasional** jika `fund_id` kosong) |
| **Alokasi penerimaan** | ✅ Boleh sebagai dana tujuan | ✅ Boleh sebagai dana tujuan |

Pesan error saat biaya bank memakai dana restricted:

> *Biaya bank tidak boleh dibebankan ke Dana Amanah khusus "…" (restricted). Gunakan Dana Operasional.*

Implementasi: `app/Domains/Expense/Validators/BankFeeValidator.php`.

### 4. Ringkasan singkat

```
Restricted   = "Uang ini untuk tujuan X" — dilindungi dari pemakaian sembarang (termasuk biaya bank)
Unrestricted = "Uang umum lembaga"     — boleh dipakai lebih fleksibel, termasuk beban operasional
```

---

## Dana Sistem (bukan tipe ketiga)

Selain dana master buatan user, SIMA punya **dana sistem** (`is_system = true`, tidak boleh dihapus/diubah). Semua dana sistem bertipe **`unrestricted`** (lihat `config/sima.php` → `system_funds`):

| `system_key` | Nama | Fungsi |
|--------------|------|--------|
| `suspense` | Dana Belum Dialokasikan | Penampung sementara penerimaan sebelum dialokasikan |
| `operational` | Dana Operasional | Default penanggung biaya administrasi bank & beban operasional internal |
| `bank_admin` | Dana Biaya Administrasi Bank | Opsional — khusus beban admin/transfer bank |
| `opening_equity` | Saldo Awal | Lawan akuntansi saat posting saldo awal kas/bank |

**Dana Operasional** sering muncul di UI filter/laporan, tetapi di database **bukan** nilai enum `type = 'operational'`. Itu adalah dana sistem dengan `system_key = operational` dan `type = unrestricted`.

---

## Kapan memilih tipe apa?

| Situasi | Pilih |
|---------|-------|
| Donatur menyerahkan dana dengan niat/program tertentu | **`restricted`** |
| Donasi umum tanpa peruntukan ketat | **`unrestricted`** |
| Beban administrasi bank, listrik kantor, dll. | Bebankan ke dana **`unrestricted`** (biasanya Dana Operasional) — **jangan** restricted |
| Laporan saldo per peruntukan donatur | Buat satu dana **`restricted`** per peruntukan |

---

## Referensi kode

| Topik | Lokasi |
|-------|--------|
| Skema & komentar tipe | `database/migrations/2026_06_29_070003_create_funds_table.php` |
| Validasi create/update master | `app/Http/Requests/Master/StoreFundRequest.php` |
| Dana sistem | `config/sima.php` → `system_funds`, `database/seeders/SystemFundSeeder.php` |
| Larangan biaya bank ke restricted | `app/Domains/Expense/Validators/BankFeeValidator.php` |
| Default fund biaya bank | `app/Domains/Expense/Services/BankFeeService.php` |
| UI master Dana Amanah | `frontend/src/lib/resources/definitions/fund.ts` |

---

## Lihat juga

- [README.md — Konsep Inti](../README.md#konsep-inti)
- [ARCHITECTURE.md — Aliran data finansial](ARCHITECTURE.md#aliran-data-finansial)
- [BACKLOG.md — Pekerjaan belum selesai](BACKLOG.md)
