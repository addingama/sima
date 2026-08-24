<?php

namespace Tests\Feature\Api;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FundTransferApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Account $account;

    private Fund $fromFund;

    private Fund $toFund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSimaBasics();

        $this->admin = $this->makeUser('admin');
        $this->account = $this->makeAccount($this->admin, ['code' => 'BANK-FTF', 'name' => 'Bank Transfer Dana', 'type' => 'bank']);
        $this->fromFund = $this->makeFund($this->admin, ['code' => 'OPS-FTF', 'name' => 'Dana Operasional']);
        $this->toFund = $this->makeFund($this->admin, ['code' => 'ADM-FTF', 'name' => 'Dana Biaya Bank']);
        $this->seedOpening($this->account, $this->fromFund, '500000.00');
    }

    #[Test]
    public function it_creates_posts_and_reverses_fund_transfer_without_changing_account_balance(): void
    {
        $this->actingAsRole('bendahara');

        $create = $this->postJson('/api/fund-transfers', [
            'transfer_date' => now()->toDateString(),
            'from_fund_id' => $this->fromFund->id,
            'to_fund_id' => $this->toFund->id,
            'amount' => '100000.00',
            'description' => 'Alokasi biaya administrasi bank',
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $id = $create->json('data.id');
        $code = $create->json('data.transfer_number');
        $this->assertMatchesRegularExpression('/^FTF\/'.date('Y').'\/\d{6}$/', $code);

        $this->postJson("/api/fund-transfers/{$id}/post")
            ->assertOk()
            ->assertJsonPath('data.status', 'posted');

        $ledger = app(LedgerService::class);
        $this->assertSame('500000.00', $ledger->balanceForAccount($this->account->id));
        $this->assertSame('400000.00', $ledger->balanceForFund($this->fromFund->id));
        $this->assertSame('100000.00', $ledger->balanceForFund($this->toFund->id));

        $this->postJson("/api/fund-transfers/{$id}/reverse", ['reason' => 'Salah klasifikasi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'reversed');

        $this->assertSame('500000.00', $ledger->balanceForAccount($this->account->id));
        $this->assertSame('500000.00', $ledger->balanceForFund($this->fromFund->id));
        $this->assertSame('0.00', $ledger->balanceForFund($this->toFund->id));
    }

    #[Test]
    public function it_rejects_same_source_and_destination_fund(): void
    {
        $this->actingAsRole('bendahara');

        $this->postJson('/api/fund-transfers', [
            'transfer_date' => now()->toDateString(),
            'from_fund_id' => $this->fromFund->id,
            'to_fund_id' => $this->fromFund->id,
            'amount' => '10000.00',
            'description' => 'Tujuan sama',
        ])->assertStatus(422);
    }

    #[Test]
    public function it_rejects_post_when_source_fund_balance_insufficient(): void
    {
        $this->actingAsRole('bendahara');

        $create = $this->postJson('/api/fund-transfers', [
            'transfer_date' => now()->toDateString(),
            'from_fund_id' => $this->fromFund->id,
            'to_fund_id' => $this->toFund->id,
            'amount' => '900000.00',
            'description' => 'Melebihi saldo',
        ])->assertCreated();

        $this->postJson('/api/fund-transfers/'.$create->json('data.id').'/post')
            ->assertStatus(422);
    }
}
