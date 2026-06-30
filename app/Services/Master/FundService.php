<?php

namespace App\Services\Master;

use App\Domains\Ledger\Services\LedgerService;
use App\Exceptions\DomainException;
use App\Models\Fund;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FundService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Fund
    {
        return Fund::create([
            ...$data,
            'created_by' => $actor->id,
            'is_system' => false,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Fund $fund, array $data): Fund
    {
        if ($fund->is_system) {
            throw new DomainException('Dana sistem tidak dapat diubah.');
        }

        $fund->update($data);

        return $fund->refresh();
    }

    public function delete(Fund $fund): void
    {
        if (bccomp($this->ledger->balanceForFund($fund->id), '0', 2) !== 0) {
            throw new DomainException('Dana dengan saldo tidak nol tidak dapat dihapus.');
        }

        $fund->delete();
    }

    public function findForShow(Fund $fund): Fund
    {
        $fund->setAttribute('balance', $this->ledger->balanceForFund($fund->id));

        return $fund;
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Fund::query()
                ->select('funds.*')
                ->selectSub(
                    DB::table('ledger_entries')
                        ->selectRaw('COALESCE(SUM(credit),0) - COALESCE(SUM(debit),0)')
                        ->whereColumn('ledger_entries.ledger_account_id', 'funds.id')
                        ->where('ledger_entries.ledger_account_type', 'fund'),
                    'balance'
                ),
            $query,
            searchColumns: ['name', 'code'],
            sortable: ['name', 'code', 'type', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
