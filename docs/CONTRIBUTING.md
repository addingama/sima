# Contributing to SIMA

Terima kasih atas minat untuk berkontribusi. SIMA mengelola dana amanah — setiap perubahan finansial harus aman, auditable, dan dapat diverifikasi.

## Sebelum mulai

1. Baca [ARCHITECTURE.md](./ARCHITECTURE.md) untuk memahami Amanah Ledger.
2. Baca [CODING_STANDARDS.md](./CODING_STANDARDS.md).
3. Cek issue existing atau buat issue untuk diskusi fitur besar.

## Setup development

### Opsi A — Docker (disarankan)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

API: http://localhost:8080/api  
Frontend (terpisah): `cd frontend && npm install && npm run dev` → http://localhost:3000

### Opsi B — Lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve

cd frontend && npm install && npm run dev
```

### Akun uji (setelah seed)

| Email | Role |
|-------|------|
| admin@sima.test | admin |
| bendahara@sima.test | bendahara |
| verifikator@sima.test | verifikator |

Password default: `password`

## Alur kontribusi

1. **Fork** repository (jika external contributor).
2. **Branch** dari `develop` atau `main`:

   ```bash
   git checkout -b feature/nama-fitur
   ```

3. **Implement** dengan scope minimal — satu PR satu concern.
4. **Test** lokal:

   ```bash
   ./vendor/bin/pint --test
   php artisan test
   cd frontend && npm run check && npm run build
   ```

5. **Commit** dengan pesan jelas:

   ```
   feat(receipts): add filter by donor on index API
   fix(ledger): reject negative fund balance on allocation
   ```

6. **Push** dan buat **Pull Request** ke branch target.
7. Tunggu **CI** hijau — lihat `.github/workflows/ci.yml`.

## Pull request checklist

### Semua PR

- [ ] CI lulus (Pint, PHPUnit, Biome, build)
- [ ] Tidak commit `.env`, credential, atau file generated
- [ ] Dokumentasi diupdate bila mengubah API/deployment

### PR backend finansial

- [ ] Logic di service layer, bukan controller
- [ ] `DB::transaction` untuk operasi multi-step
- [ ] Invariant saldo non-negatif terjaga
- [ ] Feature test untuk happy path + edge case reversal
- [ ] Permission/RBAC sesuai `config/sima.php`

### PR frontend

- [ ] Reuse komponen CRUD/report existing
- [ ] Permission UI selaras backend
- [ ] Tidak hardcode dummy data jika endpoint tersedia

## Hal yang perlu persetujuan maintainer

- Perubahan skema `ledger_entries` atau trigger immutability
- Perubahan alur status transaksi (draft → posted → …)
- Penambahan role/permission baru
- Breaking change API

## Pelaporan bug

Sertakan:

- Langkah reproduksi
- Expected vs actual behavior
- Log relevan (tanpa data sensitif)
- Environment (Docker / lokal, versi PHP/Node)

## Pertanyaan

Buka issue dengan label `question` atau diskusi di PR draft.

## Lisensi

Dengan berkontribusi, Anda setuju bahwa kontribusi akan dilisensikan sesuai lisensi project (lihat file LICENSE jika ada).
