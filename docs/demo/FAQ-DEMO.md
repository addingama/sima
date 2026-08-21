# FAQ Demonstrasi SIMA

Jawaban siap pakai saat demo produk di depan pimpinan, bendahara, auditor, atau calon pengguna.

**Dokumen terkait:** [SIMA-Presentasi-Demo.pptx](./SIMA-Presentasi-Demo.pptx) · [PANDUAN-MULAI.md](../PANDUAN-MULAI.md) · [DANA-AMANAH.md](../DANA-AMANAH.md)

---

## Persiapan 5 menit sebelum demo

| Item | Jawaban singkat |
|------|----------------|
| URL | Frontend `http://localhost:3000` (atau staging) |
| Akun | `bendahara@sima.test`, `verifikator@sima.test`, `ketua@sima.test`, `donatur@sima.test` — password: `password` |
| Data contoh | `php artisan sima:seed-demo` (setelah `migrate --seed`) |
| Reset data | `php artisan migrate:fresh --seed && php artisan sima:seed-demo` |
| Jangan | Jangan demo di production dengan akun `*@sima.test` |

---

## Konsep inti

### Apa bedanya Kas/Bank dan Dana Amanah?

**Kas/Bank** = di *mana* uang berada (kas kantor, rekening BCA).  
**Dana Amanah** = *untuk apa* uang boleh dipakai (zakat, yatim, umum).

Satu uang selalu punya keduanya. Total Kas/Bank harus sama dengan total Dana Amanah.

### Apa itu Restricted vs Unrestricted?

| | Restricted | Unrestricted |
|---|------------|--------------|
| Arti | Terikat niat/peruntukan | Dana umum lembaga |
| Contoh | Zakat, Infaq yatim | Dana sosial umum, operasional |
| Biaya bank | Tidak boleh dibebankan ke sini | Boleh (biasanya Dana Operasional) |

### Apa itu Amanah Ledger?

Buku besar yang menjadi **satu-satunya sumber kebenaran**. Saldo tidak diisi manual di form master; selalu dihitung dari jurnal. Transaksi tidak dihapus — dibatalkan dengan **reversal**.

### Mengapa tidak boleh hapus transaksi?

Supaya jejak audit utuh. Koreksi = void/reversal yang menghasilkan jurnal penyeimbang, bukan mengedit/menghapus sejarah.

---

## Operasional harian

### Siapa yang boleh melakukan apa?

- **Bendahara:** input master & transaksi harian, submit
- **Verifikator:** verifikasi pengeluaran
- **Ketua:** setujui penerimaan & pengeluaran (posting ke ledger)
- **Admin:** setup, user, saldo awal go-live
- **Auditor:** lihat laporan & audit
- **Donatur:** portal riwayat sendiri

### Kenapa penerimaan belum menambah saldo?

Belum **approved** (ketua). Status `draft` / `submitted` belum posting ledger.

### Kenapa pengeluaran ditolak / gagal approve?

Biasanya: saldo Dana Amanah tidak cukup, saldo kas tidak cukup, atau workflow belum `verified`.

### Satu pengeluaran bisa dari banyak dana?

Ya. Sumber dana multi-baris (`expense_fund_sources`); total sumber harus = nominal pengeluaran.

### Apa fungsi Vendor?

Master penerima pembayaran. Saat buat pengeluaran, pilih vendor agar nama penerima & riwayat mudah dilacak.

### Apa fungsi Event/Program?

Melacak kegiatan/anggaran (opsional). **Bukan** pengganti Dana Amanah — batas penggunaan tetap dari dana.

### Transfer antar rekening mengubah Dana Amanah?

Tidak. Hanya memindahkan lokasi fisik uang (kas A → bank B).

### Biaya administrasi bank dari dana mana?

Dari dana **unrestricted** (default: Dana Operasional sistem). Tidak boleh dari dana restricted.

---

## Approval & kontrol

### Kenapa ada banyak status pengeluaran?

`draft → submitted → verified → approved`.  
Verifikator mengecek kelengkapan; ketua menyetujui dampak keuangan (posting ledger).

### Di mana antrian approval?

Menu **Approval** — tab sesuai wewenang role (verifikator lihat antrian verify; ketua lihat penerimaan submitted & pengeluaran verified).

### Bisakah edit transaksi yang sudah approved?

Tidak langsung. Koreksi lewat **reverse**, lalu buat transaksi baru bila perlu.

---

## Saldo, laporan, rekonsiliasi

### Saldo di form Kas/Bank bisa diisi manual?

Tidak. Saldo dari ledger. Saldo awal go-live lewat menu **Saldo Awal** (admin).

### Apa artinya “Seimbang” di dashboard?

Total Kas/Bank = Total Dana Amanah, dan total debit = total credit di ledger. Jika tidak seimbang → ada masalah data yang harus diselidiki.

### Rekonsiliasi bank mengubah buku besar?

Tidak. Rekonsiliasi mencocokkan rekening koran vs sistem; menyelesaikan rekonsiliasi **tidak** mengubah ledger.

### Liabilitas operasional itu apa?

Register utang/kewajiban (belum bayar). Arus kas terjadi saat diselesaikan lewat pengeluaran yang sudah approved.

---

## Portal donatur

### Donatur bisa melihat semua donasi lembaga?

Tidak. Hanya penerimaan **approved** yang tertaut ke data donaturnya.

### Bagaimana menautkan akun login ke donatur?

Ada **dua cara** (hasilnya sama: `donors.user_id`):

**A. Dari master Donatur (bendahara / admin)**  
1. Pastikan sudah ada user dengan role **donatur** (dibuat admin di Pengaturan → Users).  
2. Buka **Master Data → Donatur → Edit**.  
3. Field **Akun Login Portal** — pilih user tersebut → Simpan.

**B. Dari Pengaturan Users (admin)**  
1. Buat/edit user, pilih role **Donatur**.  
2. Muncul field **Tautkan Donatur** — pilih master donatur → Simpan.

Setelah tertaut, login dengan akun itu lalu buka `/dashboard/portal-donatur`.

---

## Teknis & go-live (pertanyaan sering dari IT)

### Stack apa?

Backend Laravel 11 + MySQL; frontend Next.js + TypeScript; auth Sanctum + RBAC Spatie.

### Apakah ada API?

Ya. REST API + Swagger `/api/documentation` + koleksi Postman di `docs/postman/`.

### Bagaimana buat admin produksi tanpa seeder demo?

`php artisan sima:create-admin` — jangan pakai `*@sima.test` di production.

### Data demo aman diulang?

Ya untuk lokal: `migrate:fresh --seed` lalu `sima:seed-demo`. Ledger append-only → seed ulang butuh DB bersih.

### Apa yang belum / opsional?

Lihat `docs/BACKLOG.md` (notifikasi email approval, beberapa polish P2/P3). Core operasional utama sudah bisa didemokan end-to-end.

---

## Jawaban “sulit” (siap diucapkan)

**Q: Apa bedanya dengan Excel / buku kas biasa?**  
A: Excel mencatat angka; SIMA menjaga *aturan amanah* — dana tidak bisa keluar dari peruntukan yang salah, sejarah tidak bisa dihapus diam-diam, dan saldo selalu bisa dibuktikan dari ledger.

**Q: Apakah sistem menjamin pengeluaran “secara syariat/niat” 100% benar?**  
A: Sistem membatasi **secara teknis** (dana mana yang boleh dipakai, saldo cukup, biaya bank tidak boleh dari restricted). Kepatuhan program/keputusan niat tetap kebijakan lembaga — SIMA memberi jejak dan kontrol agar kebijakan itu bisa diaudit.

**Q: Kalau bendahara salah input?**  
A: Selama belum approve, bisa dikoreksi/ditolak. Setelah approve, pakai reverse dengan alasan — jejak tetap ada.

**Q: Berapa lama implementasi?**  
A: Setelah master & saldo awal siap, transaksi harian bisa langsung. Waktu terbesar biasanya Fase 0: sepakati daftar dana, rekening, dan worksheet opening — bukan coding.

**Q: Apakah data kami aman?**  
A: Role-based access, ledger immutable, audit trail. Detail hosting/backup ada di `docs/DEPLOYMENT.md`.

---

## Cheat sheet satu layar (print untuk presenter)

```
Login bendahara → Dashboard chart
Master Dana (jelaskan restricted) + Kas
Penerimaan → submit → ketua approve → saldo naik
Pengeluaran multi-dana → verify → approve → saldo turun
Dashboard: rekonsiliasi “Seimbang”
Portal donatur@sima.test
FAQ lengkap: docs/demo/FAQ-DEMO.md
```
