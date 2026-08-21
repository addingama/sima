<?php

namespace Database\Seeders;

use App\Domains\Expense\Services\BankFeeService;
use App\Domains\Expense\Services\ExpenseService;
use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Opening\Services\OpeningBalanceService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Domains\Reconciliation\Services\ReconciliationService;
use App\Domains\Transfer\Services\TransferService;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Donor;
use App\Models\Fund;
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
