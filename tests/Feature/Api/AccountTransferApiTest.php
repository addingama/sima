<?php

namespace Tests\Feature\Api;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountTransferApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $from;

    private Account $to;

    private Fund $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->from = $this->makeAccount($this->admin, ['code' => 'CASH-01', 'name' => 'Kas Kantor']);
        $this->to = $this->makeAccount($this->admin, ['code' => 'BANK-01', 'name' => 'Bank Utama', 'type' => 'bank']);
        $this->fund = $this->makeFund($this->admin);
        $this->seedOpening($this->from, $this->fund, '500000.00');
    }

    #[Test]
    public function it_creates_posts_and_reverses_transfer(): void
    {
        $this->actingAsRole('bendahara');

        $create = $this->postJson('/api/account-transfers', [
            'transfer_date' => now()->toDateString(),
            'from_account_id' => $this->from->id,
            'to_account_id' => $this->to->id,
            'amount' => '100000.00',
            'description' => 'Setor ke bank',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $id = $create->json('data.id');
        $code = $create->json('data.transfer_number');
        $this->assertMatchesRegularExpression('/^TRF\/'.date('Y').'\/\d{6}$/', $code);

        $this->postJson("/api/account-transfers/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $this->assertSame('400000.00', app(LedgerService::class)->balanceForAccount($this->from->id));
        $this->assertSame('100000.00', app(LedgerService::class)->balanceForAccount($this->to->id));
        $this->assertSame('500000.00', app(LedgerService::class)->balanceForFund($this->fund->id));

        $this->postJson("/api/account-transfers/{$id}/reverse", ['reason' => 'Salah rekening'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reversed');

        $this->assertSame('500000.00', app(LedgerService::class)->balanceForAccount($this->from->id));
        $this->assertSame('0.00', app(LedgerService::class)->balanceForAccount($this->to->id));
    }

    #[Test]
    public function it_rejects_same_source_and_destination(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/account-transfers', [
            'transfer_date' => now()->toDateString(),
            'from_account_id' => $this->from->id,
            'to_account_id' => $this->from->id,
            'amount' => '10000.00',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_post_when_source_balance_insufficient(): void
    {
        $this->actingAsRole('bendahara');

        $create = $this->postJson('/api/account-transfers', [
            'transfer_date' => now()->toDateString(),
            'from_account_id' => $this->from->id,
            'to_account_id' => $this->to->id,
            'amount' => '900000.00',
        ])->assertCreated();

        $this->postJson('/api/account-transfers/'.$create->json('data.id').'/post')
            ->assertStatus(422);
    }
}
