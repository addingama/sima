# Testing Frontend SIMA

Frontend SIMA menggunakan **Vitest**, **jsdom**, dan **React Testing Library**. Fokus utama test adalah perilaku yang memengaruhi validitas transaksi, otorisasi tampilan, transformasi payload API, dan interaksi pengguna.

## Menjalankan test

```bash
cd frontend
npm test                 # seluruh test satu kali
npm run test:watch       # mode interaktif saat pengembangan
npm run test:coverage    # test + laporan coverage HTML
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
```

## Struktur saat ini

- Setup global: `frontend/src/test/setup.ts`
- Konfigurasi runner: `frontend/vitest.config.mts`
- Unit/component test: berdampingan dengan source di `frontend/src/**`
- E2E: belum dikonfigurasi; rekomendasi runner adalah Playwright ketika fixture auth dan database test sudah tersedia.

