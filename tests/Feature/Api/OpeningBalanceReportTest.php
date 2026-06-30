<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpeningBalanceReportTest extends TestCase
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
    public function auditor_can_view_opening_balance_report(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'reference' => 'Go-live',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '1000000.00'],
            ],
        ])->assertCreated();

        $this->actingAsRole('auditor');

        $response = $this->getJson('/api/reports/opening-balances')
            ->assertOk()
            ->assertJsonPath('meta.total_amount', '1000000.00')
            ->assertJsonPath('meta.batch_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.opening_date', '2026-01-01')
            ->assertJsonPath('data.0.account_code', $this->account->code)
            ->assertJsonPath('data.0.fund_code', $this->fund->code)
            ->assertJsonPath('data.0.amount', '1000000.00');

        $this->assertStringStartsWith('OPN/', $response->json('data.0.batch_number'));
    }

    #[Test]
    public function report_filters_by_opening_date_range(): void
    {
        $account2 = $this->makeAccount($this->admin, ['code' => 'BNK-2', 'name' => 'Bank 2']);

        $this->actingAsRole('admin');

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-01-01',
            'lines' => [
                ['account_id' => $this->account->id, 'fund_id' => $this->fund->id, 'amount' => '100000.00'],
            ],
        ])->assertCreated();

        $this->postJson('/api/opening-balances', [
            'opening_date' => '2026-02-01',
            'lines' => [
                ['account_id' => $account2->id, 'fund_id' => $this->fund->id, 'amount' => '200000.00'],
            ],
        ])->assertCreated();

        $this->actingAsRole('auditor');

        $this->getJson('/api/reports/opening-balances?from=2026-02-01&to=2026-02-01')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total_amount', '200000.00')
            ->assertJsonPath('data.0.opening_date', '2026-02-01');
    }

    #[Test]
    public function donatur_cannot_view_opening_balance_report(): void
    {
        $this->actingAsRole('donatur');

        $this->getJson('/api/reports/opening-balances')->assertForbidden();
    }
}
