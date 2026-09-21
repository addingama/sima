# Pengajuan Bantuan — Kontrak modul

Dokumen ini mengunci keputusan produk untuk **modul kasus bantuan**. Bukan bagian Amanah Ledger.

**Status:** backend domain (#41) + tautan pengeluaran 1:1 (#42) + Kanban UI (#43).

**Bukan** portal pemohon, **bukan** Trello bebas kolom, **bukan** pengeluaran. Kartu = satu penerima. Uang keluar hanya lewat Pengeluaran SIMA yang tertaut.

Identitas pengguna: tabel `users` + Sanctum yang sama. Tidak ada password terpisah.

---

## Keputusan terkunci

1. Bounded context terpisah dari `ledger_entries`. Modul ini tidak mem-posting jurnal.
2. Satu akun login SIMA (permission baru, bukan user baru).
3. Penerima **tidak** mengajukan dan **tidak** punya akun. Petugas memasukkan data dari **rekomendasi orang**.
4. Verifikasi = **kerja lapangan data**, bukan ceklis setuju/tolak. Verifikator assigned **mengisi/memperbaiki data dasar** yang kosong atau salah saat rekomendasi, **dan mengumpulkan lampiran pendukung**. Rekening **tidak wajib** (jalur utama: tunai).
5. Alur: rekomendasi → verifikasi → approval → bendahara → serah terima tunai + foto → selesai.
6. **Selesai** hanya jika foto penyerahan ada **dan** pengeluaran tertaut berstatus `approved`.
7. **Satu pengeluaran per penerima.** Kas fisik boleh disiapkan sekaligus; jurnal tidak boleh digabung.
8. Verifikator **per penerima** (`assigned_verifier_id`), bukan antrian “semua yang ber-role verifikator”.
9. Assignment **boleh kosong** saat input; **wajib terisi** sebelum kirim ke Approval.
10. Cara bayar default **tunai**. Transfer adalah pengecualian per kartu.

---

## Status (kolom Kanban)

Nilai teknis di kiri, label UI di kanan.

| Status | Kolom | Arti |
|--------|--------|------|
| `draft` | Rekomendasi | Baru diinput; verifikator boleh kosong |
| `verification` | Verifikasi | Ada verifikator; sedang dilengkapi |
| `pending_approval` | Menunggu approval | Berkas siap diputuskan |
| `approved` | Siap diserahkan | Ketua setuju; belum selesai serah terima |
| `completed` | Selesai | Foto ada + pengeluaran `approved` |
| `returned` | (kembali ke Verifikasi) | Ketua/verifikator kembalikan; status teknis `verification` + flag/catatan pengembalian |
| `rejected` | Ditolak | Tidak dilanjutkan; arsip, bukan hapus |

Kartu **tanpa** `assigned_verifier_id` tetap di `draft`. Mengisi verifikator tidak otomatis pindah kolom; petugas/koordinator menekan **Kirim ke verifikasi**.

`paid` bukan kolom. “Sudah dibayar” = pengeluaran tertaut `approved`. Untuk tunai, itu syarat **Selesai** bersama foto.

---

## Field wajib per tahap

Field yang tidak disebut di tahap itu **opsional** (boleh diisi lebih awal).

### `draft` — buat pengajuan

| Field | Wajib | Catatan |
|-------|--------|---------|
| Nama penerima | Ya | Bukan user sistem |
| Nominal usulan | Ya | `decimal(18,2)`, `> 0` |
| Alasan / jenis bantuan | Ya | Teks |
| Nama pemberi rekomendasi | Ya | Bukan akun; simpan nama (+ kontak opsional) |
| Cara bayar | Ya (default) | Default `cash`. `transfer` = pengecualian |
| `assigned_verifier_id` | Tidak | User yang punya `grant.verify` |
| Alamat kasar / wilayah | Tidak | |
| Telepon penerima | Tidak | |
| `program_id` | Tidak | Tautan Event/Program SIMA |
| Catatan | Tidak | |
| Lampiran awal | Tidak | Boleh; kelengkapan bukan syarat kirim ke verifikasi |

Penerima **bukan** `vendors`. Data tinggal di pengajuan. Master penerima bantuan terpisah hanya jika nanti orang yang sama sering muncul.

### Kirim `draft` → `verification`

| Syarat | |
|--------|--|
| `assigned_verifier_id` terisi | User aktif, permission `grant.verify` |
| Nominal usulan & nama penerima masih valid | |

### `verification` — sebelum naik ke approval

Diisi/dikonfirmasi oleh **verifikator yang di-assign** (atau dikembalikan ke tahap ini). Petugas input **tidak** mengedit kartu setelah dikirim ke verifikasi.

Verifikator boleh mengubah field data penerima & berkas (nama, telepon, alamat, NIK, alasan, pemberi rekomendasi, cara bayar, rekening, program, catatan). **Nominal usulan** tidak diubah di tahap ini; koreksi nominal memakai `verified_amount`.

| Field | Wajib | Catatan |
|-------|--------|---------|
| Identitas penerima | Ya | Minimal NIK **atau** dokumen identitas terlampir (`title: identity`) |
| Alamat / cara menemui | Ya | Cukup untuk serah terima tunai; isi jika kosong di rekomendasi |
| Telepon penerima | Tidak | Dilengkapi bila belum ada |
| Nominal hasil verifikasi | Ya | Default = usulan; boleh diubah, `> 0` |
| Cara bayar | Ya | |
| Rekening (bank, no. rekening, atas nama) | Hanya jika `transfer` | Tidak menahan kartu tunai |
| Catatan verifikator | Ya | Jejak “sudah dicek” |
| Lampiran pendukung | Kebijakan lembaga | Identitas = NIK **atau** file `identity`. Selain itu: KK, foto kondisi, surat RT, dll. (judul bebas) |

### `pending_approval` — keputusan ketua

| Field | Wajib saat setujui |
|-------|-------------------|
| Nominal disetujui | Ya, default = hasil verifikasi; ketua boleh turunkan (`> 0`, tidak boleh naik tanpa kembali ke verifikasi) |
| Catatan keputusan | Tidak (wajib jika tolak / kembalikan) |

Tolak: alasan wajib. Kembalikan ke verifikasi: alasan wajib; `assigned_verifier_id` tetap (boleh reassign).

### `approved` — bendahara

Bukan field di kartu saja: **buat Pengeluaran SIMA** tertaut 1:1.

| Field pengeluaran | Aturan |
|-------------------|--------|
| `payee` | Nama penerima dari pengajuan |
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
| Petugas yang menyerahkan | Ya (`user_id`) |
| Foto penyerahan | Ya, ≥ 1 lampiran (pola `attachments` morph) |
| Pengeluaran tertaut | Ya, status `approved` |

Tanpa foto: tidak **Selesai**. Tanpa pengeluaran `approved`: tidak **Selesai**. Foto tidak menggantikan jurnal.

Transfer (pengecualian): foto opsional; **Selesai** jika pengeluaran `approved` (+ bukti transfer sebagai lampiran pengeluaran, sesuai modul existing).

---

## Siapa boleh menggeser

| Transisi | Siapa | Syarat tambahan |
|----------|--------|-----------------|
| Buat `draft` | `grant.create` | — |
| Isi / ganti verifikator | `grant.assign` | Target user punya `grant.verify`. Pembuat kartu juga boleh assign selama `draft` / `verification` |
| `draft` → `verification` | Pembuat, `grant.assign`, atau admin | Verifikator sudah terisi |
| Lengkapi data + lampiran | Verifikator **yang di-assign**, atau admin | Record-level; termasuk perbaiki data dasar yang kosong |
| `verification` → `pending_approval` | Verifikator assigned, atau admin | Checklist verifikasi lengkap |
| Setujui → `approved` | `grant.approve` (ketua) | — |
| Tolak → `rejected` | `grant.approve` | Alasan wajib |
| Kembalikan → `verification` | `grant.approve` atau verifikator assigned | Alasan wajib |
| Buat pengeluaran tertaut | `disbursement.create` (bendahara / asisten) | Kartu `approved`; belum ada pengeluaran |
| `approved` → `completed` | `grant.handover` | Foto + pengeluaran `approved` |
| Reassign verifikator | `grant.assign` | Audit trail; satu verifikator aktif |

Verifikator **tidak** meng-assign diri ke kartu orang lain (bukan model claim-dari-pool).

Penerima tidak login. Role `donatur` tidak mendapat permission grant.

---

## Permission

Format sama dengan `config/sima.php`: `modul.aksi`.

| Permission | Arti |
|------------|------|
| `grant.view` | Lihat kartu (scope: lihat bawah) |
| `grant.create` | Input rekomendasi |
| `grant.update` | Ubah field yang masih boleh di status itu (draft: pembuat; verifikasi: verifikator assigned) |
| `grant.assign` | Isi / ganti `assigned_verifier_id` |
| `grant.verify` | Syarat **boleh ditugaskan** + kerjakan kartu assigned |
| `grant.approve` | Setujui / tolak / kembalikan |
| `grant.handover` | Isi serah terima + foto + tandai selesai |
| `grant.reject` | Opsional alias; boleh digabung ke `grant.approve` |

**Scope lihat (record-level):**

| Role | Default tampilan Kanban |
|------|-------------------------|
| Verifikator | Kartu **assigned ke saya** + (opsional toggle) yang saya pernah handle |
| Petugas input | Kartu yang saya buat + yang perlu saya assign |
| Ketua, bendahara, admin, auditor | Semua kartu |

Koordinator = admin atau ketua (`grant.assign` + lihat semua). Kartu belum di-assign terlihat oleh pemegang `grant.assign`, bukan oleh verifikator lain.

### Pemetaan role existing

Role baru tidak wajib di rilis pertama. Petugas input = `asisten_bendahara`.

| Role | Grant |
|------|--------|
| `admin` | semua (`*`) |
| `asisten_bendahara` | `view`, `create`, `update` (punya sendiri / draft), `assign` (kartu yang dibuatnya), `handover` |
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
- Drag hanya untuk transisi yang diizinkan; gagal jika syarat field belum lengkap (pesan jelas).
- Detail kartu = formulir + lampiran + riwayat assignment/keputusan.
- Filter verifikator: “kartu saya” vs semua (sesuai permission).

Demo template `/dashboard/kanban` **bukan** fondasi modul ini (data dummy).

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
