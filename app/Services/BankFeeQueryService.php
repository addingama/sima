<?php

namespace App\Services;

use App\Models\BankFee;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BankFeeQueryService
{
    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            BankFee::query()->with(['account:id,code,name', 'fund:id,code,name']),
            $query,
            searchColumns: ['fee_number', 'description'],
            sortable: ['fee_date', 'fee_number', 'amount', 'created_at'],
            defaultSort: 'fee_date',
            filterCallbacks: [
                'from' => fn ($q, $v) => $q->whereDate('fee_date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('fee_date', '<=', $v),
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
