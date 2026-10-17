# Materi demonstrasi SIMA

| File | Isi |
|------|-----|
| [SIMA-Presentasi-Demo.pptx](./SIMA-Presentasi-Demo.pptx) | Slide presentasi produk (26 slide, widescreen 16:9; video alur di slide 11–13, 15–16, 18) |
| [videos/dashboard-dana.mp4](./videos/dashboard-dana.mp4) | Dashboard, Dana Amanah, kas/bank, rekonsiliasi Seimbang |
| [videos/penerimaan.mp4](./videos/penerimaan.mp4) | Penerimaan + alokasi → submit → approve |
| [videos/pengeluaran.mp4](./videos/pengeluaran.mp4) | Pengeluaran → submit → verifikasi → approve |
| [videos/pengajuan-bantuan.mp4](./videos/pengajuan-bantuan.mp4) | Bantuan perorangan → selesai |
| [videos/pengajuan-bantuan-instansi.mp4](./videos/pengajuan-bantuan-instansi.mp4) | Bantuan instansi/kelompok air bersih → selesai |
| [videos/portal-donatur.mp4](./videos/portal-donatur.mp4) | Portal donatur: hanya donasi approved miliknya |
| [videos/kas-kecil-operasional.mp4](./videos/kas-kecil-operasional.mp4) | Kas kecil operasional: buat float → cair DSB → belanja PJ → tutup |
| [videos/kas-kecil-event.mp4](./videos/kas-kecil-event.mp4) | Kas kecil event: float terikat program → cair → belanja → tutup |
| [FAQ-DEMO.md](./FAQ-DEMO.md) | FAQ + cheat sheet presenter |
| [generate_pptx.py](./generate_pptx.py) | Script regenerasi PPT |

## Regenerasi PPT

```bash
python3 -m venv .venv-pptx
.venv-pptx/bin/pip install python-pptx
.venv-pptx/bin/python docs/demo/generate_pptx.py
```

## Regenerasi video demo

Rekaman memakai Playwright (bukan suite E2E CI). Membutuhkan `ffmpeg`.

```bash
cd frontend
npx playwright install chromium
npm run demo:videos
```

Hanya kas kecil (operasional + event):

```bash
cd frontend
npm run demo:petty-cash-video
```

Setelah itu regenerasi PPT agar semua video tertanam.