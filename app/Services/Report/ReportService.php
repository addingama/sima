<?php

namespace App\Services\Report;

use App\Domains\Ledger\Services\BalanceService;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Account;
use App\Models\Fund;
use App\Models\LedgerEntry;
use App\Models\OpeningBalanceLine;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ReportService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly BalanceService $balances,
    ) {}

    /** @return array{rows: Collection<int, array<string, mixed>>, total: string} */
    public function fundBalances(): array
    {
        $rows = Fund::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'is_system'])
            ->map(fn (Fund $fund) => [
                'id' => $fund->id,
                'code' => $fund->code,
                'name' => $fund->name,
                'type' => $fund->type,
                'is_system' => $fund->is_system,
                'balance' => $this->ledger->balanceForFund($fund->id),
            ]);

        return [
            'rows' => $rows,
            'total' => $rows->reduce(fn (string $c, $r) => bcadd($c, $r['balance'], 2), '0.00'),
        ];
    }

    /** @return array{rows: Collection<int, array<string, mixed>>, total: string} */
    public function accountBalances(): array
    {
        $rows = Account::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $this->ledger->balanceForAccount($account->id),
            ]);

        return [
            'rows' => $rows,
            'total' => $rows->reduce(fn (string $c, $r) => bcadd($c, $r['balance'], 2), '0.00'),
        ];
    }

    public function ledger(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            LedgerEntry::query(),
            $query,
            searchColumns: ['reference'],
            sortable: ['created_at', 'id', 'debit', 'credit'],
            defaultSort: 'created_at',
            filterCallbacks: [
                'ledger_account_type' => fn ($q, $v) => $q->where('ledger_account_type', $v),
                'ledger_account_id' => fn ($q, $v) => $q->where('ledger_account_id', $v),
                'account_id' => fn ($q, $v) => $q->where('ledger_account_type', LedgerAccountType::ACCOUNT->value)->where('ledger_account_id', $v),
                'fund_id' => fn ($q, $v) => $q->where('ledger_account_type', LedgerAccountType::FUND->value)->where('ledger_account_id', $v),
                'transaction_type' => fn ($q, $v) => $q->where('transaction_type', $v),
                'from' => fn ($q, $v) => $q->whereDate('created_at', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('created_at', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @return array<string, mixed> */
    public function reconciliationSummary(): array
    {
        $totalAccounts = $this->balances->totalAccountBalances();
        $totalFunds = $this->balances->totalFundBalances();
        $totalDebits = $this->ledger->totalDebits();
        $totalCredits = $this->ledger->totalCredits();

        return [
            'total_kas_bank' => $totalAccounts,
            'total_dana_amanah' => $totalFunds,
            'total_debit' => $totalDebits,
            'total_credit' => $totalCredits,
            'selisih_kas_dana' => bcsub($totalAccounts, $totalFunds, 2),
            'selisih_debit_credit' => bcsub($totalDebits, $totalCredits, 2),
            'seimbang' => bccomp($totalAccounts, $totalFunds, 2) === 0
                && bccomp($totalDebits, $totalCredits, 2) === 0,
        ];
    }

    /** @return array{paginator: LengthAwarePaginator, total_amount: string, batch_count: int} */
    public function openingBalances(ListQueryDto $query): array
    {
        $builder = OpeningBalanceLine::query()
            ->select('opening_balance_lines.*')
            ->join(
                'opening_balance_batches',
                'opening_balance_batches.id',
                '=',
                'opening_balance_lines.opening_balance_batch_id'
            )
            ->with([
                'account:id,code,name',
                'fund:id,code,name',
                'batch' => fn ($q) => $q->with('postedBy:id,name'),
            ]);

        $builder = ListQueryApplier::apply(
            $builder,
            $query,
            searchColumns: [
                'opening_balance_batches.batch_number',
                'opening_balance_batches.reference',
            ],
            sortable: [
                'opening_balance_batches.opening_date',
                'opening_balance_batches.batch_number',
                'opening_balance_lines.line_number',
                'opening_balance_lines.amount',
            ],
            defaultSort: 'opening_balance_batches.opening_date',
            defaultDirection: 'desc',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('opening_balance_batches.opening_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('opening_balance_batches.opening_date', '<=', $v),
                'account_id' => fn ($q, $v) => $q->where('opening_balance_lines.account_id', $v),
                'fund_id' => fn ($q, $v) => $q->where('opening_balance_lines.fund_id', $v),
            ],
        );

        $summaryQuery = clone $builder;
        $totalAmount = bcadd((string) ($summaryQuery->clone()->sum('opening_balance_lines.amount') ?? '0'), '0', 2);
        $batchCount = (int) $summaryQuery->clone()
            ->select('opening_balance_batches.id')
            ->distinct()
            ->count('opening_balance_batches.id');

        $paginator = $builder->paginate($query->perPage, ['*'], 'page', $query->page);

        return [
            'paginator' => $paginator,
            'total_amount' => $totalAmount,
            'batch_count' => $batchCount,
        ];
    }

    /** @return array<string, mixed> */
    public function fundStatement(int $fundId, ?string $from, ?string $to): array
    {
        $query = LedgerEntry::query()
            ->where('ledger_account_type', LedgerAccountType::FUND->value)
            ->where('ledger_account_id', $fundId);

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        $credit = bcadd((string) ($query->clone()->sum('credit') ?? '0'), '0', 2);
        $debit = bcadd((string) ($query->clone()->sum('debit') ?? '0'), '0', 2);

        return [
            'fund' => Fund::find($fundId),
            'inflow' => $credit,
            'outflow' => $debit,
            'net' => bcsub($credit, $debit, 2),
        ];
    }
}
