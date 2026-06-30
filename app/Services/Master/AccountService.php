<?php

namespace App\Services\Master;

use App\Domains\Ledger\Services\LedgerService;
use App\Exceptions\DomainException;
use App\Models\Account;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Account
    {
        return Account::create([...$data, 'created_by' => $actor->id]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Account $account, array $data): Account
    {
        $account->update($data);

        return $account->refresh();
    }

    public function delete(Account $account): void
    {
        if (bccomp($this->ledger->balanceForAccount($account->id), '0', 2) !== 0) {
            throw new DomainException('Akun dengan saldo tidak nol tidak dapat dihapus.');
        }

        $account->delete();
    }

    public function findForShow(Account $account): Account
    {
        $account->setAttribute('balance', $this->ledger->balanceForAccount($account->id));

        return $account;
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Account::query()
                ->select('accounts.*')
                ->selectSub(
                    DB::table('ledger_entries')
                        ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0)')
                        ->whereColumn('ledger_entries.ledger_account_id', 'accounts.id')
                        ->where('ledger_entries.ledger_account_type', 'account'),
                    'balance'
                ),
            $query,
            searchColumns: ['name', 'code', 'bank_name', 'account_number'],
            sortable: ['name', 'code', 'type', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
