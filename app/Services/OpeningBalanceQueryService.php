<?php

namespace App\Services;

use App\Models\OpeningBalanceBatch;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OpeningBalanceQueryService
{
    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            OpeningBalanceBatch::query()->with([
                'lines.account:id,code,name',
                'lines.fund:id,code,name',
            ]),
            $query,
            searchColumns: ['batch_number', 'reference'],
            sortable: ['opening_date', 'batch_number', 'total_amount', 'created_at'],
            defaultSort: 'opening_date',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('opening_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('opening_date', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
