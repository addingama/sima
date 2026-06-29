# Coding Standards — SIMA

Standar ini berlaku untuk backend Laravel dan frontend Next.js. Ikuti konvensi existing codebase sebelum menambah pola baru.

## Prinsip bisnis (WAJIB)

1. **Ledger adalah sumber kebenaran** — saldo dihitung dari `ledger_entries`, bukan kolom statis.
2. **Tidak ada hard delete** transaksi finansial — gunakan reversal/void.
3. **Transaksi approved tidak diedit** — koreksi via reversal.
4. **Nominal uang** — `decimal(18,2)` di DB, operasi dengan `bcmath` di PHP, hindari float.
5. **Operasi finansial** — bungkus `DB::transaction()`, posting via service layer.

Baca juga `CLAUDE.md` / `AGENTS.md` di root repo.

## Backend (Laravel / PHP)

### Struktur

| Lapisan | Lokasi | Tanggung jawab |
|---------|--------|----------------|
| Controller | `app/Http/Controllers/Api/` | HTTP, delegasi ke service |
| Form Request | `app/Http/Requests/` | Validasi input |
| Service | `app/Services/` | Business logic, ledger posting |
| Model | `app/Models/` | Eloquent, relasi, audit |
| Resource | `app/Http/Resources/` | Shape response API (target refactor) |
| Policy | `app/Policies/` | Authorization per model |

### Gaya kode

- **PHP 8.2+** — typed properties, return types, constructor promotion where appropriate.
- **PSR-12** — enforced via [Laravel Pint](https://laravel.com/docs/pint).

```bash
./vendor/bin/pint          # fix
./vendor/bin/pint --test   # CI check
```

### Naming

- Controller: `ReceiptController`, method RESTful (`index`, `store`, `show`, …)
- Service: `ReceiptService`, method verb (`post`, `reverse`, `submit`)
- Permission: `receipt.create`, `disbursement.approve` (lihat `config/sima.php`)

### API response

Gunakan `ApiResponse` helper untuk envelope konsisten:

```php
return ApiResponse::success($data, 'Pesan opsional');
return ApiResponse::error('Validasi gagal', 422, $errors);
```

### Database

- Migration reversible where possible.
- Index foreign keys & kolom filter (status, date).
- Trigger immutability pada `ledger_entries` — jangan dihapus.

### Testing

```bash
php artisan test
```

- Feature test untuk alur finansial (post → reverse, invariant negatif).
- Gunakan `RefreshDatabase` + sqlite `:memory:` di CI.

## Frontend (Next.js / TypeScript)

### Struktur

```
frontend/src/
  app/              # App Router pages
  components/sima/  # Domain components (CRUD, reports)
  lib/api/          # API client & entities
  lib/resources/    # CRUD definitions per modul
  hooks/            # Shared hooks
```

### Gaya kode

- **TypeScript strict** — hindari `any`; gunakan tipe dari `lib/api/entities.ts`.
- **Biome** untuk lint & format:

```bash
cd frontend && npm run check      # lint + format check
cd frontend && npm run check:fix  # auto-fix
```

### Komponen

- Function components + hooks (bukan class components).
- Client components: `"use client"` hanya bila perlu interaktivitas.
- Reuse CRUD infrastructure (`CrudListPage`, `CrudFormPage`, …) — jangan copy-paste per modul.

### Data fetching

- TanStack Query untuk server state.
- API base URL dari `NEXT_PUBLIC_API_URL` (production: `/api` same-origin).

### UI

- shadcn/ui + template Shadcn Admin Dashboard.
- Table-first untuk data keuangan (TanStack Table).
- Format nominal: locale `id-ID`, currency IDR.

## Git & commit

- Branch: `feature/…`, `fix/…`, `docs/…`
- Commit message: imperative, concise (English or Indonesian konsisten per repo)
- PR wajib lulus CI sebelum merge

## Security checklist (PR finansial)

- [ ] Permission middleware / policy dicek
- [ ] Input divalidasi (Form Request atau validate)
- [ ] Operasi ledger dalam transaction
- [ ] Audit trail terpicu (model Auditable)
- [ ] Tidak expose data sensitif di log/error response produksi

## Referensi

- [CONTRIBUTING.md](./CONTRIBUTING.md) — alur kontribusi
- [ARCHITECTURE.md](./ARCHITECTURE.md) — diagram sistem
- Laravel docs: https://laravel.com/docs/11.x
- Next.js docs: https://nextjs.org/docs
