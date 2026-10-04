# Testing Frontend SIMA

Frontend SIMA menggunakan **Vitest**, **jsdom**, dan **React Testing Library**. Fokus utama test adalah perilaku yang memengaruhi validitas transaksi, otorisasi tampilan, transformasi payload API, dan interaksi pengguna.

## Menjalankan test

```bash
cd frontend
npm test                 # seluruh test satu kali
npm run test:watch       # mode interaktif saat pengembangan
npm run test:coverage    # test + laporan coverage HTML
npm run test:e2e         # Playwright: login dan akses lintas role
npm run test:e2e:ui      # mode UI Playwright untuk debugging lokal
```

Laporan HTML tersimpan di `frontend/coverage/index.html` dan tidak masuk Git.

## Konvensi

- Letakkan test berdampingan dengan implementasi: `nama-file.test.ts` atau `nama-komponen.test.tsx`.
- Test fungsi murni dengan input, output, dan edge case yang eksplisit.
- Test komponen berdasarkan teks, label, role, dan perilaku pengguna; jangan menguji class CSS kecuali class tersebut membawa makna perilaku.
- Gunakan data nominal sebagai string saat mewakili nilai API atau form. Jangan memperkenalkan perhitungan uang berbasis float ke business logic.
- Mock batas eksternal seperti network, waktu, atau storage; jangan mock fungsi yang sedang diuji.
- Setiap bug yang diperbaiki harus memiliki regression test jika dapat direproduksi secara deterministik.

## Prioritas coverage

1. Validasi dan normalisasi form finansial.
2. Permission dan pembatasan aksi berdasarkan role/status.
3. Parser serta formatter nominal dan tanggal.
4. API client: envelope sukses, error validasi, dan unauthorized.
5. Komponen form dan workflow finansial.
6. E2E untuk alur kritis: login, draft, submit, approve/post, dan reversal.

Coverage adalah indikator, bukan target tunggal. Branch penting dan kondisi gagal harus diuji meskipun persentase global sudah tinggi.

## Quality gate lokal

Sebelum commit perubahan frontend:

```bash
cd frontend
npm run check
npm test
npx tsc --noEmit
npm run build
npm run test:e2e
```

## Struktur saat ini

- Setup global: `frontend/src/test/setup.ts`
- Konfigurasi runner: `frontend/vitest.config.mts`
- Unit/component test: berdampingan dengan source di `frontend/src/**`
- E2E: `frontend/e2e/` dengan Playwright Chromium. Konfigurasi membangun ulang `storage/e2e.sqlite`, menjalankan seeder dasar + demo, lalu menyalakan Laravel `:8100` dan Next.js `:3100`. Database development tidak disentuh.

## E2E lintas role

Suite E2E memakai akun seed `*@sima.test` dengan password `password` dan mencakup `admin`, `asisten_bendahara`, `bendahara`, `verifikator`, `petugas_bantuan`, `ketua`, `auditor`, serta `donatur`.

Prasyarat lokal satu kali:

```bash
cd frontend
npx playwright install chromium
```

Setiap `npm run test:e2e` mereset **hanya** `storage/e2e.sqlite`. Trace, screenshot, dan video kegagalan berada di `frontend/test-results/`; laporan HTML berada di `frontend/playwright-report/`. Keduanya diabaikan Git.

Suite memakai build directory `.next-e2e` dan port `3100`, sehingga tidak berbenturan dengan Next dev normal pada port `3000`.
