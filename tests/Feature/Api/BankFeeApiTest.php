<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankFeeApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->account = $this->makeAccount($this->admin);
    }

    #[Test]
    public function it_creates_and_posts_bank_fee(): void
    {
        $operational = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);
        $this->seedOpening($this->account, $operational, '100000.00');

        $this->actingAsRole('bendahara');
        $create = $this->postJson('/api/bank-fees', [
            'fee_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '25000.00',
            'fee_type' => 'admin',
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/bank-fees/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');
    }

    #[Test]
    public function it_defers_bank_fee_when_balance_insufficient(): void
    {
        $this->actingAsRole('bendahara');
        $create = $this->postJson('/api/bank-fees', [
            'fee_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'amount' => '15000.00',
            'fee_type' => 'admin',
        ])->assertCreated();

        $id = $create->json('data.id');
        $this->postJson("/api/bank-fees/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'deferred');
    }

    #[Test]
    public function it_rejects_restricted_fund_via_api(): void
    {
        $restricted = $this->makeFund($this->admin, ['type' => 'restricted']);
        $this->actingAsRole('bendahara');

        $this->postJson('/api/bank-fees', [
            'fee_date' => now()->toDateString(),
            'account_id' => $this->account->id,
            'fund_id' => $restricted->id,
            'amount' => '10000.00',
            'fee_type' => 'admin',
        ])->assertStatus(422)->assertJsonPath('errors.code', 'domain_rule_violation');
    }
}
