<?php

namespace Database\Seeders;

use App\Domains\Expense\Services\BankFeeService;
use App\Domains\Expense\Services\ExpenseService;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Opening\Services\OpeningBalanceService;
use App\Domains\PettyCash\Services\PettyCashCategoryService;
use App\Domains\PettyCash\Services\PettyCashCloseService;
use App\Domains\PettyCash\Services\PettyCashFloatService;
use App\Domains\PettyCash\Services\PettyCashFundingService;
use App\Domains\PettyCash\Services\PettyCashTopupService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Domains\Reconciliation\Services\ReconciliationService;
use App\Domains\Transfer\Services\TransferService;
use App\Enums\PettyCashFloatPurpose;
use App\Enums\PettyCashFundingKind;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Disbursement;
use App\Models\Donor;
use App\Models\Fund;
use App\Models\PettyCashCategory;
use App\Models\PettyCashFloat;
use App\Models\Program;
use App\Models\User;
use App\Services\Master\AccountService;
use App\Services\Master\DonorService;
use App\Services\Master\FundService;
use App\Services\Master\ProgramService;
use App\Services\Master\VendorService;
use App\Services\OperationalLiabilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Data demo real-case untuk mencoba seluruh fitur UI (lokal/staging).
 *
 * Marker idempotensi: Account code DEMO-KAS.
 * Jalankan ulang: migrate:fresh --seed && php artisan sima:seed-demo
 */
class DemoDataSeeder extends Seeder
{
    public const MARKER_ACCOUNT_CODE = 'DEMO-KAS';

    public function run(
        AccountService $accounts,
        FundService $funds,
        DonorService $donors,
        VendorService $vendors,
        ProgramService $programs,
        OpeningBalanceService $openings,
        ReceiptService $receipts,
        ExpenseService $expenses,
        BankFeeService $bankFees,
        TransferService $transfers,
        ReconciliationService $reconciliations,
        OperationalLiabilityService $liabilities,
        BalanceService $balances,
        PettyCashFloatService $pettyFloats,
        PettyCashFundingService $pettyFundings,
        PettyCashTopupService $pettyTopups,
        PettyCashCloseService $pettyCloser,
        PettyCashCategoryService $pettyCategories,
    ): void {
        if (Account::query()->where('code', self::MARKER_ACCOUNT_CODE)->exists()) {
            throw new RuntimeException(
                'Data demo sudah ada (akun '.self::MARKER_ACCOUNT_CODE.'). '.
                'Reset dulu: php artisan migrate:fresh --seed && php artisan sima:seed-demo'
            );
        }

        $admin = User::role(UserRole::ADMIN->value)->first()
            ?? throw new RuntimeException('User admin belum ada. Jalankan db:seed dulu.');
        $bendahara = User::role(UserRole::BENDAHARA->value)->first() ?? $admin;
        $verifikator = User::role(UserRole::VERIFIKATOR->value)->first() ?? $admin;
        $ketua = User::role(UserRole::KETUA->value)->first() ?? $admin;
        $pj = User::role(UserRole::PETUGAS_BANTUAN->value)->first()
            ?? User::role(UserRole::VERIFIKATOR->value)->first()
            ?? $bendahara;

        $this->command?->info('Membuat master data demo…');

        $kas = $accounts->create([
            'code' => self::MARKER_ACCOUNT_CODE,
            'name' => 'Kas Kantor (Demo)',
            'type' => 'cash',
            'is_active' => true,
        ], $admin);

        $bca = $accounts->create([
            'code' => 'DEMO-BCA',
            'name' => 'BCA Operasional (Demo)',
            'type' => 'bank',
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Yayasan Demo SIMA',
            'is_active' => true,
        ], $admin);

        $zakat = $funds->create([
            'code' => 'DEMO-ZKT',
            'name' => 'Zakat Mustahik (Demo)',
            'type' => 'restricted',
            'description' => 'Dana zakat berperuntukan — contoh restricted.',
            'is_active' => true,
        ], $admin);

        $yatim = $funds->create([
            'code' => 'DEMO-YATIM',
            'name' => 'Infaq Yatim (Demo)',
            'type' => 'restricted',
            'description' => 'Infaq khusus yatim.',
            'is_active' => true,
        ], $admin);

        $umum = $funds->create([
            'code' => 'DEMO-UMUM',
            'name' => 'Dana Sosial Umum (Demo)',
            'type' => 'unrestricted',
            'description' => 'Dana umum tanpa peruntukan ketat.',
            'is_active' => true,
        ], $admin);

        $operational = Fund::findBySystemKey(Fund::KEY_OPERATIONAL)
            ?? throw new RuntimeException('Dana sistem operasional belum tersedia.');

        $donorPortal = $this->ensureDonaturUser();

        $donorBudi = $donors->create([
            'code' => 'DEMO-DON-001',
            'name' => 'Budi Santoso',
            'type' => 'individu',
            'email' => 'budi.demo@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Melati No. 1',
            'is_active' => true,
            'user_id' => $donorPortal->id,
        ], $bendahara);

        $donorLembaga = $donors->create([
            'code' => 'DEMO-DON-002',
            'name' => 'PT Peduli Sesama',
            'type' => 'lembaga',
            'email' => 'csr@peduli.example.com',
            'phone' => '0215551234',
            'is_active' => true,
        ], $bendahara);

        $vendorToko = $vendors->create([
            'code' => 'DEMO-VND-001',
            'name' => 'Toko Sembako Makmur',
            'contact_name' => 'Andi',
            'phone' => '081298765432',
            'is_active' => true,
        ], $bendahara);

        $vendorPrint = $vendors->create([
            'code' => 'DEMO-VND-002',
            'name' => 'CV Cetak Cepat',
            'contact_name' => 'Siti',
            'email' => 'order@cetakcepat.example.com',
            'is_active' => true,
        ], $bendahara);

        $program = $programs->create([
            'code' => 'DEMO-PRG-001',
            'name' => 'Bakti Sosial Ramadan',
            'fund_id' => $yatim->id,
            'description' => 'Program distribusi paket sembako yatim.',
            'budget' => '50000000.00',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'status' => 'active',
            'is_active' => true,
        ], $bendahara);

        $this->command?->info('Posting saldo awal…');

        $openings->create([
            'opening_date' => now()->subMonths(2)->toDateString(),
            'reference' => 'Saldo awal demo go-live',
            'lines' => [
                ['account_id' => $kas->id, 'fund_id' => $umum->id, 'amount' => '5000000.00'],
                ['account_id' => $bca->id, 'fund_id' => $zakat->id, 'amount' => '15000000.00'],
                ['account_id' => $bca->id, 'fund_id' => $yatim->id, 'amount' => '10000000.00'],
                ['account_id' => $bca->id, 'fund_id' => $operational->id, 'amount' => '3000000.00'],
            ],
        ], $admin);

        $this->command?->info('Membuat penerimaan (draft / submitted / approved)…');

        $receiptDraft = $receipts->create([
            'receipt_date' => now()->subDays(3)->toDateString(),
            'account_id' => $bca->id,
            'donor_id' => $donorLembaga->id,
            'channel' => 'transfer',
            'reference_number' => 'TRF-DEMO-001',
            'amount' => '2500000.00',
            'description' => '[Demo] Draft — belum disubmit',
        ], [
            ['fund_id' => $umum->id, 'amount' => '2500000.00'],
        ], $bendahara);

        $receiptSubmitted = $receipts->create([
            'receipt_date' => now()->subDays(2)->toDateString(),
            'account_id' => $bca->id,
            'donor_id' => $donorBudi->id,
            'channel' => 'transfer',
            'amount' => '1000000.00',
            'description' => '[Demo] Submitted — menunggu persetujuan ketua',
        ], [
            ['fund_id' => $zakat->id, 'amount' => '1000000.00'],
        ], $bendahara);
        $receipts->submit($receiptSubmitted, $bendahara);

        $receiptApproved = $receipts->create([
            'receipt_date' => now()->subDays(10)->toDateString(),
            'account_id' => $bca->id,
            'donor_id' => $donorBudi->id,
            'channel' => 'qris',
            'amount' => '750000.00',
            'description' => '[Demo] Approved — donasi Budi (portal)',
        ], [
            ['fund_id' => $yatim->id, 'amount' => '500000.00', 'program_id' => $program->id],
            ['fund_id' => $umum->id, 'amount' => '250000.00'],
        ], $bendahara);
        $receipts->submit($receiptApproved, $bendahara);
        $receipts->approve($receiptApproved, $ketua, 'Disetujui (data demo)');

        $this->command?->info('Membuat pengeluaran (draft / submitted / verified / approved)…');

        $expenseDraft = $expenses->create([
            'disbursement_date' => now()->subDay()->toDateString(),
            'account_id' => $kas->id,
            'program_id' => $program->id,
            'vendor_id' => $vendorToko->id,
            'amount' => '500000.00',
            'category' => 'Operasional',
            'description' => '[Demo] Draft pengeluaran',
        ], [
            ['fund_id' => $umum->id, 'amount' => '500000.00', 'program_id' => $program->id],
        ], $bendahara);

        $expenseSubmitted = $expenses->create([
            'disbursement_date' => now()->subDays(2)->toDateString(),
            'account_id' => $bca->id,
            'vendor_id' => $vendorPrint->id,
            'amount' => '350000.00',
            'category' => 'Publikasi',
            'description' => '[Demo] Submitted — menunggu verifikasi',
        ], [
            ['fund_id' => $umum->id, 'amount' => '350000.00'],
        ], $bendahara);
        $expenses->submit($expenseSubmitted, $bendahara);

        $expenseVerified = $expenses->create([
            'disbursement_date' => now()->subDays(3)->toDateString(),
            'account_id' => $bca->id,
            'vendor_id' => $vendorToko->id,
            'amount' => '1200000.00',
            'category' => 'Santunan',
            'description' => '[Demo] Verified — menunggu persetujuan ketua',
        ], [
            ['fund_id' => $yatim->id, 'amount' => '1200000.00', 'program_id' => $program->id],
        ], $bendahara);
        $expenses->submit($expenseVerified, $bendahara);
        $expenses->verify($expenseVerified, $verifikator);

        $expenseApproved = $expenses->create([
            'disbursement_date' => now()->subDays(7)->toDateString(),
            'account_id' => $bca->id,
            'vendor_id' => $vendorToko->id,
            'amount' => '2000000.00',
            'category' => 'Santunan',
            'description' => '[Demo] Approved — sudah diposting ke ledger',
        ], [
            ['fund_id' => $zakat->id, 'amount' => '2000000.00'],
        ], $bendahara);
        $expenses->submit($expenseApproved, $bendahara);
        $expenses->verify($expenseApproved, $verifikator);
        $expenses->approve($expenseApproved, $ketua, 'Disetujui (data demo)');

        $this->command?->info('Biaya bank, transfer, liabilitas, rekonsiliasi…');

        $fee = $bankFees->create([
            'fee_date' => now()->subDays(5)->toDateString(),
            'account_id' => $bca->id,
            'fee_type' => 'admin',
            'amount' => '25000.00',
            'description' => '[Demo] Biaya admin bulanan BCA',
        ], $bendahara);
        $bankFees->post($fee, $bendahara);

        $transfer = $transfers->create([
            'transfer_date' => now()->subDays(4)->toDateString(),
            'from_account_id' => $bca->id,
            'to_account_id' => $kas->id,
            'amount' => '1000000.00',
            'description' => '[Demo] Tarik tunai kas kecil dari BCA',
        ], $bendahara);
        $transfers->post($transfer, $bendahara);

        $liabilities->create([
            'liability_date' => now()->subDays(6)->toDateString(),
            'fund_id' => $operational->id,
            'creditor' => 'Vendor Jasa Kebersihan',
            'amount' => '750000.00',
            'description' => '[Demo] Utang operasional outstanding — settle lewat pengeluaran',
            'due_date' => now()->addDays(14)->toDateString(),
        ], $bendahara);

        $statementBalance = $balances->accountBalance($bca->id);
        $reconciliations->create([
            'account_id' => $bca->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
            'statement_balance' => $statementBalance,
            'notes' => '[Demo] Draft rekonsiliasi — selisih 0 (statement = sistem)',
        ], $bendahara);

        $this->command?->info('Kas kecil (kategori, float operasional/event, top-up, tutup)…');
        $this->seedPettyCashDemo(
            $pettyFloats,
            $pettyFundings,
            $pettyTopups,
            $pettyCloser,
            $pettyCategories,
            $expenses,
            $bendahara,
            $verifikator,
            $ketua,
            $pj,
            $bca,
            $umum,
            $yatim,
            $program,
        );

        // Keep intentional mid-pipeline drafts for UI queues.
        unset($receiptDraft, $expenseDraft, $vendorPrint);

        $this->command?->newLine();
        $this->command?->info('Demo data siap. Ringkasan saldo:');
        $this->command?->table(
            ['Akun / Dana', 'Saldo'],
            [
                ["Kas {$kas->code}", $balances->accountBalance($kas->id)],
                ["Bank {$bca->code}", $balances->accountBalance($bca->id)],
                ["Dana {$zakat->code}", $balances->fundBalance($zakat->id)],
                ["Dana {$yatim->code}", $balances->fundBalance($yatim->id)],
                ["Dana {$umum->code}", $balances->fundBalance($umum->id)],
                ["Dana {$operational->code}", $balances->fundBalance($operational->id)],
            ],
        );

        $this->command?->info('Portal donatur: login donatur@sima.test / password (tertaut ke Budi Santoso).');
        $this->command?->info('Antrian Approval: 1 penerimaan submitted, 1 pengeluaran submitted, 1 verified.');
        $this->command?->info('Kas Kecil: login petugas.bantuan@sima.test / password sebagai PJ (float reporting + top-up pending).');
    }

    private function seedPettyCashDemo(
        PettyCashFloatService $pettyFloats,
        PettyCashFundingService $pettyFundings,
        PettyCashTopupService $pettyTopups,
        PettyCashCloseService $pettyCloser,
        PettyCashCategoryService $pettyCategories,
        ExpenseService $expenses,
        User $bendahara,
        User $verifikator,
        User $ketua,
        User $pj,
        Account $bca,
        Fund $umum,
        Fund $yatim,
        Program $program,
    ): void {
        $this->call(PettyCashCategorySeeder::class);

        $internet = PettyCashCategory::query()->where('code', 'INTERNET')->firstOrFail();
        $atk = PettyCashCategory::query()->where('code', 'ATK')->firstOrFail();
        $transport = PettyCashCategory::query()->where('code', 'TRANSPORT')->firstOrFail();
        $konsumsi = PettyCashCategory::query()->where('code', 'KONSUMSI')->firstOrFail();

        // Ensure demo-friendly labels exist even if seeder already ran with defaults.
        $pettyCategories->update($internet, ['name' => 'Internet & Pulsa']);

        // 1) Float operasional bulanan — reporting + baris + top-up pending
        $opsFloat = $pettyFloats->create([
            'title' => '[Demo] Operasional Tim Lapangan — '.now()->translatedFormat('F Y'),
            'purpose' => PettyCashFloatPurpose::OPERATIONAL->value,
            'custodian_user_id' => $pj->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'notes' => 'Anggaran bulanan internet, ATK, transport PJ lapangan.',
        ], $bendahara);

        $this->fundAndApprovePettyCash(
            $pettyFundings,
            $expenses,
            $opsFloat,
            $bendahara,
            $verifikator,
            $ketua,
            $bca,
            $umum,
            '750000.00',
            PettyCashFundingKind::INITIAL,
            '[Demo] Cair operasional kas kecil',
        );

        $opsFloat->refresh();
        $pettyFloats->addLine($opsFloat, [
            'category_id' => $internet->id,
            'spent_on' => now()->subDays(5)->toDateString(),
            'amount' => '150000.00',
            'description' => '[Demo] Tagihan internet kantor lapangan',
        ], $pj);
        $pettyFloats->addLine($opsFloat, [
            'category_id' => $atk->id,
            'spent_on' => now()->subDays(3)->toDateString(),
            'amount' => '85000.00',
            'description' => '[Demo] ATK (kertas, tinta)',
        ], $pj);
        $pettyFloats->addLine($opsFloat, [
            'category_id' => $transport->id,
            'spent_on' => now()->subDay()->toDateString(),
            'amount' => '120000.00',
            'description' => '[Demo] Bensin & ojek survey mustahik',
        ], $pj);

        $pettyTopups->request(
            $opsFloat->refresh(),
            '200000.00',
            '[Demo] Sisa hampir habis — minta top-up untuk tagihan internet sisa bulan',
            $pj,
        );

        // 2) Float event — funded, belanja, lalu ditutup dengan sisa dikembalikan
        $eventFloat = $pettyFloats->create([
            'title' => '[Demo] Event Bakti Sosial Ramadan',
            'purpose' => PettyCashFloatPurpose::EVENT->value,
            'custodian_user_id' => $pj->id,
            'program_id' => $program->id,
            'period_start' => now()->subDays(14)->toDateString(),
            'period_end' => now()->subDays(2)->toDateString(),
            'notes' => 'Float event terpisah dari operasional bulanan.',
        ], $bendahara);

        $this->fundAndApprovePettyCash(
            $pettyFundings,
            $expenses,
            $eventFloat,
            $bendahara,
            $verifikator,
            $ketua,
            $bca,
            $yatim,
            '500000.00',
            PettyCashFundingKind::INITIAL,
            '[Demo] Cair event bakti sosial',
        );

        $eventFloat->refresh();
        $pettyFloats->addLine($eventFloat, [
            'category_id' => $konsumsi->id,
            'spent_on' => now()->subDays(10)->toDateString(),
            'amount' => '275000.00',
            'description' => '[Demo] Konsumsi panitia & penerima',
        ], $pj);
        $pettyFloats->addLine($eventFloat, [
            'category_id' => $transport->id,
            'spent_on' => now()->subDays(9)->toDateString(),
            'amount' => '125000.00',
            'description' => '[Demo] Sewa angkutan distribusi paket',
        ], $pj);

        $pettyCloser->close($eventFloat->refresh(), [
            'returned_amount' => '100000.00',
            'account_id' => $bca->id,
            'receipt_date' => now()->subDays(2)->toDateString(),
        ], $pj);

        // 3) Float draft — menunggu cair bendahara
        $pettyFloats->create([
            'title' => '[Demo] Operasional Tim B — menunggu cair',
            'purpose' => PettyCashFloatPurpose::OPERATIONAL->value,
            'custodian_user_id' => $pj->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'notes' => 'Draft float — bendahara belum mencairkan DSB.',
        ], $bendahara);
    }

    private function fundAndApprovePettyCash(
        PettyCashFundingService $pettyFundings,
        ExpenseService $expenses,
        PettyCashFloat $float,
        User $bendahara,
        User $verifikator,
        User $ketua,
        Account $account,
        Fund $fund,
        string $amount,
        PettyCashFundingKind $kind,
        string $description,
    ): void {
        $pettyFundings->createDisbursement(
            $float->load('custodian'),
            [
                'amount' => $amount,
                'disbursement_date' => now()->subDays(8)->toDateString(),
                'account_id' => $account->id,
                'description' => $description,
            ],
            [['fund_id' => $fund->id, 'amount' => $amount]],
            $bendahara,
            $kind,
        );

        $disbursementId = $float->fresh()->fundings()->latest('id')->firstOrFail()->disbursement_id;
        $disbursement = Disbursement::query()->findOrFail($disbursementId);

        $expenses->submit($disbursement, $bendahara);
        $expenses->verify($disbursement->refresh(), $verifikator);
        $expenses->approve($disbursement->refresh(), $ketua, 'Disetujui (data demo kas kecil)');
    }

    private function ensureDonaturUser(): User
    {
        $user = User::updateOrCreate(
            ['email' => 'donatur@sima.test'],
            [
                'name' => 'Donatur Portal',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $user->syncRoles([UserRole::DONATUR->value]);

        Donor::query()->where('user_id', $user->id)->update(['user_id' => null]);

        return $user;
    }
}
