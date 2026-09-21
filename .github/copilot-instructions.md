# Instruksi Copilot / Codex / agent GitHub

Ikuti **AGENTS.md** di root repositori. File itu identik dengan `CLAUDE.md`. Riwayat chat Cursor tidak ada di sini.

| Topik | Sumber |
|-------|--------|
| Aturan ledger & status kode | `AGENTS.md` |
| Pengajuan bantuan | `docs/BANTUAN.md` |
| Backlog | `docs/BACKLOG.md` |
| Role & permission | `config/sima.php` |
| Frontend | `frontend/AGENTS.md` |

Catatan singkat:

- `asisten_bendahara` input/submit; `bendahara` approve/reverse keuangan. Jangan tulis tes yang mengira bendahara tidak boleh approve penerimaan.
- Modul bantuan tidak mem-posting `ledger_entries`. Satu pengeluaran per pengajuan.
- Frontend belum punya tes unit/e2e; CI hanya Biome + build.
- Kerja bantuan yang belum merge `main`: branch `feature/grant-applications` (PR #44, issue #41–#45).
