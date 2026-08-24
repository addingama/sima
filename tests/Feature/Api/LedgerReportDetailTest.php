<?php

namespace Tests\Feature\Api;

use App\Enums\LedgerAccountType;
use App\Enums\TransactionType;
use App\Models\LedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LedgerReportDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();
    }

    #[Test]
    public function ledger_report_includes_account_labels_and_side(): void
    {
        $admin = $this->actingAsRole('admin');
        ['account' => $account, 'fund' => $fund] = $this->makeFinancialFixtures($admin);

        LedgerEntry::query()->insert([
            [
                'transaction_type' => TransactionType::RECEIPT->value,
                'transaction_id' => 1,
                'ledger_account_type' => LedgerAccountType::ACCOUNT->value,
                'ledger_account_id' => $account->id,
                'debit' => '100000.00',
                'credit' => '0.00',
                'reference' => 'Penerimaan RCP/TEST/001',
                'created_at' => now(),
            ],
            [
                'transaction_type' => TransactionType::RECEIPT->value,
                'transaction_id' => 1,
                'ledger_account_type' => LedgerAccountType::FUND->value,
                'ledger_account_id' => $fund->id,
                'debit' => '0.00',
                'credit' => '100000.00',
                'reference' => 'Penerimaan RCP/TEST/001',
                'created_at' => now(),
            ],
        ]);

        $this->getJson('/api/reports/ledger?per_page=10&sort=created_at&direction=desc')
            ->assertOk()
            ->assertJsonFragment([
                'transaction_type_label' => 'Penerimaan',
                'side_label' => 'Debit',
                'ledger_account_type_label' => 'Kas/Bank',
            ])
            ->assertJsonFragment([
                'transaction_type_label' => 'Penerimaan',
                'side_label' => 'Kredit',
                'ledger_account_type_label' => 'Dana Amanah',
            ])
            ->assertJsonPath('data.0.ledger_account_name', fn ($name) => is_string($name) && $name !== '');
    }
}
