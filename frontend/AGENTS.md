# Frontend SIMA

Aturan permanen ada di [`../AGENTS.md`](../AGENTS.md). Riwayat chat Cursor **tidak** terlihat di sini.

Kontrak bantuan: [`../docs/BANTUAN.md`](../docs/BANTUAN.md). Backlog: [`../docs/BACKLOG.md`](../docs/BACKLOG.md).

## Stack

Next.js App Router, TypeScript, Tailwind, shadcn/ui (template Shadcn Admin Dashboard). Jangan buat layout dashboard dari nol.

## Tes

Belum ada Vitest / Playwright / Testing Library. CI frontend: `npm run check` (Biome) + `npm run build`.

Perilaku bisnis (status, filter, ringkasan laporan, permission) diuji di PHPUnit:

- `tests/Feature/Api/GrantApplicationApiTest.php`
- `tests/Feature/Api/GrantApplicationReportTest.php`

## Path penting

| Fitur | Lokasi |
|-------|--------|
| Kanban bantuan | `src/app/(main)/dashboard/bantuan/` |
| Laporan bantuan | `src/app/(main)/dashboard/reports/bantuan/` |
| Definisi laporan | `src/lib/reports/definitions.ts`, `fetch-helpers.ts` |
| Client API | `src/lib/api/client.ts` |
| Sidebar | `src/navigation/sidebar/sidebar-items.ts` |
| Resource grant | `src/lib/resources/definitions/grant-application.ts` |

## API lokal

`./dev.sh` di root: Laravel `:8000`, Next `:3000`. `frontend/.env` harus `NEXT_PUBLIC_API_URL=http://localhost:8000/api` (bukan Docker `:8080` kecuali stack Docker).

## UX bantuan (keputusan terkunci)

- Board: tab **Antrian** vs **Arsip** (Selesai/Ditolak).
- Toggle **Yang saya verifikasi** = `assigned_verifier_id` user login (bukan “kartu saya”).
- Preview gambar: jangan cache blob URL yang sudah di-revoke; zoom/pan di panel.
- PDF: buka tab baru (`window.open` sinkron, lalu object URL `application/pdf`).
