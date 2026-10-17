# Kas Kecil — Kontrak modul

Dokumen ini mengunci keputusan produk untuk **subsistem float kas kecil**. Bounded context terpisah dari posting jurnal; uang keluar/masuk hanya lewat Pengeluaran/Penerimaan SIMA yang tertaut.

**Issues:** domain [#59](https://github.com/addingama/sima/issues/59) · funding [#60](https://github.com/addingama/sima/issues/60) · top-up [#61](https://github.com/addingama/sima/issues/61) · tutup [#62](https://github.com/addingama/sima/issues/62) · UI [#63](https://github.com/addingama/sima/issues/63) · laporan [#64](https://github.com/addingama/sima/issues/64)

**Bukan** imprest abadi tanpa tutup. **Bukan** role baru `pj_kegiatan`. **Bukan** approval laporan belanja oleh bendahara.

Identitas pengguna: tabel `users` + Sanctum. PJ = penugasan `custodian_user_id` pada float.

---

## Keputusan terkunci

1. Model **uang muka**: cair/top-up memposting ledger via **Pengeluaran** (`DSB`). Baris belanja PJ **tidak** memposting `ledger_entries`.
2. Satu float = satu kantong (operasional bulanan **atau** satu event). Jangan campur.
3. Field `purpose`: `operational` | `event`. Operasional wajib `period_start`/`period_end`. Event disarankan `program_id`.
4. **Tanpa approval pencatatan.** PJ mengisi baris dan menutup sendiri saat seimbang.
5. **PJ = assignment**, bukan role baru. User existing (verifikator, petugas bantuan, dll.) boleh jadi custodian.
6. **Top-up** dalam scope: PJ minta tambah; bendahara cair DSB tambahan ke float yang sama.
7. Pendanaan **1 float : N DSB** (`petty_cash_fundings`, kind `initial` | `topup`).
8. Invariant tutup: `sum(lines) + returned_amount = funded_total` (bcmath). `funded_total` = sum nominal funding dengan DSB `approved`.
9. Kategori belanja = master `petty_cash_categories` dikelola sistem (`category.manage`). Baris wajib `category_id` aktif.
10. MVP: **satu Dana Amanah per float**. Multi-fund campur di luar scope.
11. Nomor dokumen float: prefix `KSK` via `DocumentNumberService`.
12. Dua event bersamaan = dua float; PJ pilih float dulu, **bukan** pilih event per baris.

---

## Status float

| Status | Arti |
|--------|------|
| `draft` | Belum ada funding initial dengan DSB approved |
| `reporting` | Minimal satu DSB funding approved; PJ mencatat baris |
| `closed` | Seimbang; receipt sisa ditautkan jika `returned_amount > 0` |
| `cancelled` | Dibatalkan; reverse DSB terpisah di modul Pengeluaran |

Tidak ada status antrean review laporan.

---

## Permission

| Permission | Arti |
|------------|------|
| `petty_cash.view` | Lihat float (scope policy) |
| `petty_cash.create` | Buat float + assign custodian |
| `petty_cash.update` | Ubah float/baris (batas status + scope) |
| `petty_cash.close` | Tutup float |
| `petty_cash.topup_request` | Minta top-up |
| `petty_cash.fund` | Cair/tolak top-up / tautkan DSB |
| `petty_cash.category.manage` | CRUD master kategori |

Role `donatur` tidak mendapat `petty_cash.*`.

Scope `visibleTo`: keuangan (admin/bendahara/asisten/auditor) lihat semua; selain itu hanya float dengan `custodian_user_id = auth.id`.

---

## Cara pakai singkat

- **Operasional bulanan:** satu float per PJ per bulan → cair → catat → (top-up) → tutup → float baru bulan berikutnya.
- **Event:** float terpisah per event, meskipun PJ sama.
- **Laporan:** ringkasan keluar bendahara → drill per float → baris PJ + funding DSB + kategori. Lihat issue #64.

---

## Di luar scope MVP

- Imprest tanpa tutup periode
- Auto-generate float bulanan
- Re-alokasi Dana Amanah per baris kategori
- Multi-custodian / multi-fund per float
