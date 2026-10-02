# Pengajuan Bantuan — Kontrak modul

Dokumen ini mengunci keputusan produk untuk **modul kasus bantuan**. Bukan bagian Amanah Ledger.

**Status:** backend domain (#41) + tautan pengeluaran 1:1 (#42) + Kanban UI (#43) + laporan (#45).

**Bukan** portal pemohon, **bukan** Trello bebas kolom, **bukan** pengeluaran. Kartu = satu penerima manfaat (perorangan atau organisasi/instansi). Uang keluar hanya lewat Pengeluaran SIMA yang tertaut.

Identitas pengguna: tabel `users` + Sanctum yang sama. Tidak ada password terpisah.

---

## Keputusan terkunci

1. Bounded context terpisah dari `ledger_entries`. Modul ini tidak mem-posting jurnal.
2. Satu akun login SIMA (permission baru, bukan user baru).
3. Penerima manfaat **tidak** mengajukan dan **tidak** punya akun. Pengajuan dibuat oleh user SIMA di sekitar calon penerima yang memiliki `grant.create`; identitas pengaju berasal dari `created_by`, bukan input nama bebas.
4. Penerima manfaat dapat berupa **perorangan** atau **organisasi/instansi**. Organisasi informal tetap dapat diajukan dan dokumen organisasi tidak wajib.
5. Verifikasi = **kerja lapangan data**, bukan ceklis setuju/tolak. Verifikator assigned **mengisi/memperbaiki data dasar** yang kosong atau salah saat rekomendasi, **dan mengumpulkan lampiran pendukung**. Rekening **tidak wajib** (jalur utama: tunai).
6. Pengaju biasanya sekaligus menjadi verifikator dan petugas serah terima. Jika pengaju memiliki `grant.verify` dan/atau `grant.handover`, assignment terkait otomatis diisi ke pengaju. Tahap pengajuan dan verifikasi tetap terpisah dan tidak boleh dilompati.
7. Permission `grant.create`, `grant.verify`, `grant.handover`, dan `grant.assign` tetap terpisah. Assignment otomatis ke diri sendiri tidak memerlukan `grant.assign`; penugasan/pengalihan kepada user lain hanya oleh pemegang `grant.assign`.
8. Alur: rekomendasi → verifikasi → approval → bendahara → serah terima tunai + foto → selesai.
9. **Selesai** hanya jika pengeluaran tertaut berstatus `approved` dan bukti penyaluran sesuai cara bayar tersedia: foto penyerahan untuk tunai, bukti transfer untuk transfer.
10. **Satu pengeluaran per penerima.** Kas fisik boleh disiapkan sekaligus; jurnal tidak boleh digabung.
11. Verifikator (`assigned_verifier_id`) dan petugas serah terima (`assigned_handover_id`) ditetapkan **per penerima**, bukan antrian bebas.
12. Assignment **boleh kosong** saat input. Verifikator wajib terisi sebelum kirim ke Verifikasi; petugas serah terima wajib terisi sebelum ketua menyetujui.
13. Cara bayar default **tunai**. Transfer adalah pengecualian per kartu.

---

## Status (kolom Kanban)

Nilai teknis di kiri, label UI di kanan.

| Status | Kolom | Arti |
|--------|--------|------|
| `draft` | Rekomendasi | Baru diinput; assignment boleh kosong atau otomatis ke pengaju yang berhak |
| `verification` | Verifikasi | Ada verifikator; sedang dilengkapi |
| `pending_approval` | Menunggu approval | Berkas siap diputuskan |
| `approved` | Siap diserahkan | Ketua setuju; belum selesai serah terima |
| `completed` | Selesai | Pengeluaran `approved` + bukti penyaluran sesuai cara bayar |
| `returned` | (kembali ke Verifikasi) | Ketua/verifikator kembalikan; status teknis `verification` + flag/catatan pengembalian |
| `rejected` | Ditolak | Tidak dilanjutkan; arsip, bukan hapus |

Kartu **tanpa** `assigned_verifier_id` tetap di `draft`. Mengisi verifikator tidak otomatis pindah kolom; petugas/koordinator menekan **Kirim ke verifikasi**.

`paid` bukan kolom. “Sudah dibayar” = pengeluaran tertaut `approved`. Untuk tunai, itu syarat **Selesai** bersama foto penyerahan; untuk transfer, bersama bukti transfer.

---

## Field wajib per tahap

Field yang tidak disebut di tahap itu **opsional** (boleh diisi lebih awal).

### `draft` — buat pengajuan

| Field | Wajib | Catatan |
|-------|--------|---------|
| Jenis penerima (`beneficiary_type`) | Ya | `individual` atau `organization` |
| Nama penerima | Ya | Nama orang atau nama organisasi/instansi; bukan user sistem |
| Nominal usulan | Ya | `decimal(18,2)`, `> 0` |
| Alasan / jenis bantuan | Ya | Teks |
| Pengaju (`created_by`) | Otomatis | User login dengan `grant.create`; tidak diinput ulang |
| Sumber rekomendasi eksternal | Tidak | Nama dan kontak pihak luar jika pengaju meneruskan rekomendasi orang lain |
| Cara bayar | Ya (default) | Default `cash`. `transfer` = pengecualian |
| `assigned_verifier_id` | Tidak | Otomatis = pengaju jika punya `grant.verify`; selain itu dapat diisi pemegang `grant.assign` |
| `assigned_handover_id` | Tidak | Otomatis = pengaju jika punya `grant.handover`; wajib sebelum approval |
| Alamat kasar / wilayah | Tidak | |
| Telepon penerima / kontak PIC | Tidak | Boleh dilengkapi saat verifikasi |
| PIC organisasi | Tidak | Nama dan hubungan dengan organisasi boleh belum lengkap saat draft |
| `program_id` | Tidak | Tautan Event/Program SIMA |
| Catatan | Tidak | |
| Lampiran awal | Tidak | Boleh; kelengkapan bukan syarat kirim ke verifikasi |

Penerima **bukan** `vendors`. Data tinggal di pengajuan. Master penerima bantuan terpisah hanya jika nanti penerima yang sama sering muncul.

### Kirim `draft` → `verification`

| Syarat | |
|--------|--|
| `assigned_verifier_id` terisi | User aktif, permission `grant.verify` |
| Nominal usulan & nama penerima masih valid | |

### `verification` — sebelum naik ke approval

Diisi/dikonfirmasi oleh **verifikator yang di-assign** (atau dikembalikan ke tahap ini). Pengaju dan verifikator boleh merupakan user yang sama, tetapi aksi kirim ke verifikasi dan selesaikan verifikasi tetap terpisah. Pengaju yang bukan verifikator assigned **tidak** mengedit kartu setelah dikirim ke verifikasi.

Verifikator boleh mengubah field data penerima & berkas (jenis/nama penerima, telepon/kontak PIC, alamat, NIK, alasan, sumber rekomendasi eksternal, cara bayar, rekening, program, catatan). **Nominal usulan** tidak diubah di tahap ini; koreksi nominal memakai `verified_amount`.

| Field | Wajib | Catatan |
|-------|--------|---------|
| Identitas perorangan | Jika `individual` | Minimal NIK **atau** dokumen identitas terlampir (`title: identity`) |
| Data organisasi | Jika `organization` | Nama organisasi, alamat/cara menemui, nama PIC, kontak PIC, dan hubungan PIC dengan organisasi |
| Alamat / cara menemui | Ya | Cukup untuk serah terima tunai; isi jika kosong di rekomendasi |
| Telepon penerima | Tidak | Untuk perorangan; dilengkapi bila tersedia |
| Nominal hasil verifikasi | Ya | Default = usulan; boleh diubah, `> 0` |
| Cara bayar | Ya | |
| Rekening (bank, no. rekening, atas nama) | Hanya jika `transfer` | Rekening organisasi atau PIC diperbolehkan; rekening PIC wajib mencatat hubungan dan alasan penggunaan rekening pribadi |
| Catatan verifikator | Ya | Jejak “sudah dicek” |
| Lampiran pendukung | Kebijakan lembaga | Perorangan: identitas = NIK **atau** file `identity`. Organisasi: dokumen legalitas/pendukung tidak wajib, tetapi boleh dilampirkan jika tersedia |

### `pending_approval` — keputusan ketua

| Field | Wajib saat setujui |
|-------|-------------------|
| Nominal disetujui | Ya, default = hasil verifikasi; ketua boleh turunkan (`> 0`, tidak boleh naik tanpa kembali ke verifikasi) |
| `assigned_handover_id` | Ya; user aktif dengan `grant.handover` |
| Catatan keputusan | Tidak (wajib jika tolak / kembalikan) |

Tolak: alasan wajib. Kembalikan ke verifikasi: alasan wajib; `assigned_verifier_id` tetap (boleh reassign).

### `approved` — bendahara

Bukan field di kartu saja: **buat Pengeluaran SIMA** tertaut 1:1.

| Field pengeluaran | Aturan |
|-------------------|--------|
| `payee` | Penerima pembayaran aktual. Untuk organisasi dapat memakai nama organisasi atau PIC yang ditunjuk |
| `amount` | = nominal disetujui |
| `account_id` | Default akun **kas tunai** jika cara bayar `cash` |
| Sumber Dana Amanah | Wajib, saldo cukup (aturan pengeluaran existing) |
| `vendor_id` | Opsional; jangan auto-create vendor |
| Tautan `grant_application_id` | Wajib, unik (satu pengeluaran per pengajuan) |

Pengeluaran mengikuti alur SIMA yang sudah ada (`draft → submitted → verified → approved`). Kartu bantuan **tidak** ikut status pengeluaran kecuali saat cek syarat **Selesai**.

### `completed` — serah terima tunai

| Field | Wajib |
|-------|--------|
| Tanggal serah terima | Ya |
| Petugas yang ditugaskan | Ya (`assigned_handover_id`); user aktif dengan `grant.handover` |
| Petugas yang menyerahkan | Ya (`handover_officer_id`); pelaksana aktual, normalnya sama dengan assignment |
| Penerima aktual | Ya | Untuk organisasi: orang yang menerima bantuan; jika berbeda dari PIC, wajib ada keterangan |
| Foto penyerahan | Ya, ≥ 1 lampiran (pola `attachments` morph) |
| Pengeluaran tertaut | Ya, status `approved` |

Tanpa foto: tidak **Selesai**. Tanpa pengeluaran `approved`: tidak **Selesai**. Foto tidak menggantikan jurnal.

Transfer (pengecualian): foto opsional; **Selesai** jika pengeluaran `approved` (+ bukti transfer sebagai lampiran pengeluaran, sesuai modul existing). Untuk organisasi, rekening boleh atas nama organisasi atau PIC. Jika atas nama PIC, hubungan dengan organisasi dan alasan penggunaan rekening pribadi wajib tercatat.

---

## Siapa boleh menggeser

| Transisi | Siapa | Syarat tambahan |
|----------|--------|-----------------|
| Buat `draft` | `grant.create` | — |
| Assignment otomatis ke pengaju | Sistem | Isi verifikator dan/atau petugas serah terima hanya jika pengaju memiliki permission masing-masing; tidak memerlukan `grant.assign` |
| Isi / ganti verifikator | `grant.assign` | Target user aktif dan punya `grant.verify` |
| Isi / ganti petugas serah terima | `grant.assign` | Target user aktif dan punya `grant.handover`; perubahan setelah approval wajib alasan + audit trail |
| `draft` → `verification` | Pembuat, `grant.assign`, atau admin | Verifikator sudah terisi |
| Lengkapi data + lampiran | Verifikator **yang di-assign**, atau admin | Record-level; termasuk perbaiki data dasar yang kosong |
| `verification` → `pending_approval` | Verifikator assigned, atau admin | Checklist verifikasi lengkap |
| Setujui → `approved` | `grant.approve` (ketua) | `assigned_handover_id` sudah terisi |
| Tolak → `rejected` | `grant.approve` | Alasan wajib |
| Kembalikan → `verification` | `grant.approve` atau verifikator assigned | Alasan wajib |
| Buat pengeluaran tertaut | `disbursement.create` (bendahara / asisten) | Kartu `approved`; belum ada pengeluaran |
| `approved` → `completed` | Petugas serah terima assigned dengan `grant.handover`, atau admin | Pengeluaran `approved` + foto tunai atau bukti transfer |
| Reassign verifikator | `grant.assign` | Audit trail; satu verifikator aktif |
| Reassign petugas serah terima | `grant.assign` | Alasan + audit trail; satu petugas aktif |

Verifikator/petugas serah terima **tidak** meng-assign diri ke kartu orang lain (bukan model claim-dari-pool). Pengaju tidak dapat memilih atau mengganti user lain tanpa `grant.assign`.

Penerima tidak login. Role `donatur` tidak mendapat permission grant.

---

## Permission

Format sama dengan `config/sima.php`: `modul.aksi`.

| Permission | Arti |
|------------|------|
| `grant.view` | Lihat kartu (scope: lihat bawah) |
| `grant.create` | Buat pengajuan sebagai user SIMA; tidak otomatis memberi hak verifikasi/serah terima |
| `grant.update` | Ubah field yang masih boleh di status itu (draft: pembuat; verifikasi: verifikator assigned) |
| `grant.assign` | Isi / ganti `assigned_verifier_id` dan `assigned_handover_id` |
| `grant.verify` | Syarat **boleh ditugaskan** + kerjakan kartu assigned |
| `grant.approve` | Setujui / tolak / kembalikan |
| `grant.handover` | Syarat **boleh ditugaskan** + isi serah terima, foto, dan tandai selesai pada kartu assigned |
| `grant.reject` | Opsional alias; boleh digabung ke `grant.approve` |

**Scope lihat (record-level):**

| Aktor | Default tampilan Kanban |
|-------|-------------------------|
| Pengaju | Kartu yang saya buat |
| Verifikator | Kartu verifikasi **assigned ke saya** + (opsional toggle) yang pernah saya tangani |
| Petugas serah terima | Kartu serah terima **assigned ke saya** + (opsional toggle) yang pernah saya tangani |
| Pemegang `grant.assign`, ketua, bendahara, admin, auditor | Semua kartu sesuai kebutuhan tugas |

Koordinator = admin atau ketua (`grant.assign` + lihat semua). Kartu belum di-assign terlihat oleh pemegang `grant.assign`, bukan oleh verifikator lain.

### Pemetaan role existing

Role baru tidak wajib di rilis pertama. Kewenangan berasal dari permission dan policy record-level; user dengan role apa pun dapat menjadi pengaju/verifikator/petugas serah terima jika memperoleh permission yang sesuai. Tabel berikut adalah pemetaan awal, bukan pembatas bahwa hanya role tersebut yang boleh mengajukan.

| Role | Grant |
|------|--------|
| `admin` | semua (`*`) |
| `asisten_bendahara` | `view`, `create`, `update` (punya sendiri / sesuai assignment), `handover` |
| `bendahara` | `view` (semua), `handover`, plus pengeluaran existing |
| `verifikator` | `view` (assigned), `verify`, `update` pada kartu assigned |
| `ketua` | `view` (semua), `assign`, `approve` |
| `auditor` | `view` (semua), tanpa aksi |
| `donatur` | — |

Pengeluaran tetap memakai `disbursement.*`. Modul bantuan tidak menambahkan jalan pintas posting ledger.

---

## Relasi ke SIMA

```
Pengajuan bantuan (kasus, mutable sesuai status)
        │  setelah approved
        ▼
Pengeluaran (1:1, alur existing)
        │  setelah approved (= posted)
        ▼
ledger_entries
```

Invariant:

- Tidak ada `ledger_entries` dengan `transaction_type` baru untuk bantuan.
- Tidak hard delete pengajuan yang sudah pernah `verification` atau lebih; arsip / `rejected`.
- Reversal pengeluaran mengikuti aturan SIMA; kartu **Selesai** tidak ikut terhapus — status perlu kebijakan terpisah (kembali ke `approved` + catatan), diputuskan saat implementasi.

---

## UI Kanban

- Kolom = status di atas, tidak bisa dibuat user.
- Board default = **Antrian** (Rekomendasi → Siap diserahkan). **Selesai** dan **Ditolak** di tab Arsip.
- Cari nama/nomor; filter jenis penerima; toggle **Yang saya verifikasi** (`assigned_verifier_id` = user login) dan **Yang saya serahkan** (`assigned_handover_id` = user login).
- Kartu ringkas (jenis/nama penerima, nomor, nominal; verifikator dan petugas serah terima jika ada). Kolom discroll vertikal.
- Drag hanya untuk transisi yang diizinkan; gagal jika syarat field belum lengkap (pesan jelas).
- Detail kartu = formulir + lampiran + riwayat assignment/keputusan.

Demo template `/dashboard/kanban` **bukan** fondasi modul ini (data dummy).

---

## Laporan organisasi

Halaman **Laporan → Pengajuan Bantuan** (`/dashboard/reports/bantuan`, `GET /api/reports/grant-applications`, permission `report.view`).

Bukan papan kerja. Ini daftar yang mudah disaring dan diekspor, plus kartu ringkasan untuk rapat/audit.

| Kartu ringkasan | Arti |
|-----------------|------|
| Jumlah kartu | Semua pengajuan yang lolos filter |
| Antrian | Masih di Rekomendasi / Verifikasi / Menunggu approval |
| Usulan | Jumlah nominal rekomendasi |
| Disetujui | Nominal `approved_amount` pada status Siap diserahkan + Selesai |
| Siap diserahkan | Nominal sudah disetujui ketua, belum serah terima |
| Sudah diserahkan | Nominal status Selesai |
| Ditolak | Nominal usulan yang ditolak |

Filter: periode (tanggal pengajuan), status, jenis penerima, cara bayar, program, cari nama/nomor. Tabel bisa dikelompokkan status / jenis penerima / cara bayar / program / verifikator / petugas serah terima. Data mengikuti scope yang sama dengan Kanban (`visibleTo`).

---

## Di luar cakupan rilis pertama

- Portal pemohon / login penerima
- Board/list/card bebas seperti Trello
- Pengeluaran batch banyak penerima
- SSO IdP terpisah (Keycloak, dll.)
- Master `beneficiaries` terpisah
- Assign ketua atau bendahara per kartu

---

## Referensi

- Ledger & pengeluaran: [ARCHITECTURE.md](ARCHITECTURE.md), [DANA-AMANAH.md](DANA-AMANAH.md)
- Role & permission keuangan: `config/sima.php`
- Backlog implementasi: [BACKLOG.md](BACKLOG.md)
- Aturan agent (Codex/Copilot): [AGENTS.md](../AGENTS.md), [frontend/AGENTS.md](../frontend/AGENTS.md)

## Peta kode (untuk agent)

Sampai merge: branch `feature/grant-applications`, PR #44.

| Apa | Di mana |
|-----|---------|
| Domain PHP | `app/Domains/Grant/` (Service, Repository, Policy, Validator) |
| Model | `app/Models/GrantApplication.php` |
| List + filter `from`/`to`/`mine`/`status` | `ListGrantApplicationRequest`, `GrantApplicationRepository` |
| Laporan | `ReportService::grantApplications`, `GET /api/reports/grant-applications` |
| Kanban UI | `frontend/src/app/(main)/dashboard/bantuan/` |
| Laporan UI | `frontend/src/app/(main)/dashboard/reports/bantuan/` + `frontend/src/lib/reports/definitions.ts` |
| Tes | `tests/Feature/Api/GrantApplicationApiTest.php`, `GrantApplicationReportTest.php` |
| Tes UI | **tidak ada** |

Keputusan implementasi yang mudah terlewat:

- Filter `mine`: boolean query `"true"` di-normalisasi di `prepareForValidation`.
- Preview lampiran gambar: blob URL tidak boleh di-cache React Query setelah revoke.
- PDF: `window.open('about:blank')` sinkron, lalu ganti ke object URL `application/pdf`.
- `complete` tunai butuh attachment `title = handover` + pengeluaran tertaut `approved`.
