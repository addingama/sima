<?php

namespace Tests\Concerns;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Account;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemFundSeeder;
use Laravel\Sanctum\Sanctum;

trait SimaTestHelpers
{
    protected function seedSimaBasics(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SystemFundSeeder::class);
    }

    protected function makeUser(string $role = 'admin'): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function actingAsRole(string $role): User
    {
        $user = $this->makeUser($role);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeAccount(User $creator, array $overrides = []): Account
    {
        return Account::create(array_merge([
            'code' => 'KAS-'.uniqid(),
            'name' => 'Kas Test',
            'type' => 'cash',
            'is_active' => true,
            'created_by' => $creator->id,
        ], $overrides));
    }

    protected function makeFund(User $creator, array $overrides = []): Fund
    {
        return Fund::create(array_merge([
            'code' => 'FND-'.uniqid(),
            'name' => 'Dana Test',
            'type' => 'restricted',
            'is_active' => true,
            'created_by' => $creator->id,
        ], $overrides));
    }

    protected function seedOpening(Account $account, Fund $fund, string $amount, ?LedgerService $ledger = null): void
    {
        $ledger ??= app(LedgerService::class);
        $openingEquity = Fund::findBySystemKey(Fund::KEY_OPENING_EQUITY);

        $ledger->postOpeningBalanceLine(
            0,
            $account->id,
            $fund->id,
            $openingEquity->id,
            $amount,
            'Saldo awal uji',
        );
    }

    /** @return array{account: Account, fund: Fund, operational: Fund} */
    protected function makeFinancialFixtures(User $creator): array
    {
        $account = $this->makeAccount($creator);
        $fund = $this->makeFund($creator);
        $operational = Fund::findBySystemKey(Fund::KEY_OPERATIONAL);

        return compact('account', 'fund', 'operational');
    }
}
