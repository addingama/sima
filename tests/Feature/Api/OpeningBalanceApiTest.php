<?php

namespace Tests\Feature\Api;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpeningBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $account;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->account = $this->makeAccount($this->admin);
        $this->fund = $this->makeFund($this->admin);
    }

    #[Test]
    public function admin_can_post_opening_balance_batch(): void
    {
        $this->actingAsRole('admin');

        $response = $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'reference' => 'Go-live saldo awal',
            'lines' => [
                [
                    'account_id' => $this->account->id,
                    'fund_id' => $this->fund->id,
                    'amount' => '1500000.00',
                ],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.total_amount', '1500000.00');
        $response->assertJsonPath('data.lines.0.amount', '1500000.00');
        $this->assertStringStartsWith('OPN/', $response->json('data.batch_number'));

        $ledger = app(LedgerService::class);
        $this->assertSame('1500000.00', $ledger->balanceForAccount($this->account->id));
        $this->assertSame('1500000.00', $ledger->balanceForFund($this->fund->id));
    }

    #[Test]
    public function admin_can_post_multiple_lines_in_one_batch(): void
    {
        $account2 = $this->makeAccount($this->admin, ['code' => 'BNK-2', 'name' => 'Bank 2']);
        $fund2 = $this->makeFund($this->admin, ['code' => 'FND-2', 'name' => 'Dana 2']);

        $this->actingAsRole('admin');

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '100000.00'],
                ['account_id' => $account2->id, 'fund_id' => $fund2->id, 'amount' => '250000.00'],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.total_amount', '350000.00')
            ->assertJsonCount(2, 'data.lines');
    }

    #[Test]
    public function bendahara_cannot_post_opening_balance(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '1000.00'],
            ],
        ])->assertForbidden();
    }

    #[Test]
    public function it_rejects_suspense_and_opening_equity_funds(): void
    {
        $this->actingAsRole('admin');

        $suspense = Fund::findBySystemKey(Fund::KEY_SUSPENSE);
        $openingEquity = Fund::findBySystemKey(Fund::KEY_OPENING_EQUITY);

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $suspense->id, 'amount' => '1000.00'],
            ],
        ])->assertStatus(422)->assertJsonPath('errors.code', 'domain_rule_violation');

        $account2 = $this->makeAccount($this->admin, ['code' => 'BNK-OE', 'name' => 'Bank OE']);

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $account2->id, 'fund_id' => $openingEquity->id, 'amount' => '1000.00'],
            ],
        ])->assertStatus(422)->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function it_rejects_account_that_already_has_opening(): void
    {
        $this->seedOpening($this->account, $this->fund, '50000.00');

        $this->actingAsRole('admin');

        $account2 = $this->makeAccount($this->admin, ['code' => 'BNK-NEW', 'name' => 'Bank Baru']);

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '1000.00'],
                ['account_id' => $account2->id, 'fund_id' => $this->fund->id, 'amount' => '2000.00'],
            ],
        ])->assertStatus(422)->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function it_rejects_second_opening_for_same_account(): void
    {
        $this->actingAsRole('admin');

        $payload = [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '1000.00'],
            ],
        ];

        $this->postJson('/api/opening-balances', $payload)->assertCreated();

        $this->postJson('/api/opening-balances', $payload)
            ->assertStatus(422)
            ->assertJsonPath('errors.code', 'domain_rule_violation');
    }

    #[Test]
    public function auditor_can_list_and_view_opening_batches(): void
    {
        $this->actingAsRole('admin');
        $create = $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '75000.00'],
            ],
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->actingAsRole('auditor');

        $this->getJson('/api/opening-balances')
            ->assertOk()
            ->assertJsonPath('data.0.batch_number', $create->json('data.batch_number'));

        $this->getJson("/api/opening-balances/{$id}")
            ->assertOk()
            ->assertJsonPath('data.total_amount', '75000.00');
    }
}
