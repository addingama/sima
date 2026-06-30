# Issue #1 — P0: API posting saldo awal (opening balance)

> **GitHub:** buat issue dengan `gh issue create` lalu samakan nomor commit. Draft ini dipakai saat auth belum tersedia.

## Goal

Endpoint terkelola untuk posting saldo awal kas/bank ke ledger (`TransactionType::OPENING`), batch multi-baris (akun + dana + nominal).

## Acceptance criteria

- [x] `POST /api/opening-balances` dengan permission `opening.manage`
- [x] Body: `opening_date`, `reference`, `lines[]` (`account_id`, `fund_id`, `amount`)
- [x] Satu batch DB + posting ledger per baris dalam transaksi
- [x] Validasi: akun/dana aktif, nominal > 0, akun saldo nol sebelum opening
- [x] `GET /api/opening-balances` + detail batch
- [x] Feature tests
- [ ] Centang item backlog + tutup GitHub issue setelah merge

## Out of scope

- UI wizard go-live
- Lawan `opening_equity` per baris (backlog item terpisah)
- Laporan opening audit
