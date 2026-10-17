<?php

namespace App\Domains\PettyCash\Repositories;

use App\Models\PettyCashFloat;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PettyCashFloatRepository
{
    public function paginate(ListQueryDto $query, User $viewer): LengthAwarePaginator
    {
        return $this->applyFilters(
            $this->visibleTo($viewer)->with([
                'custodian:id,name',
                'program:id,code,name',
                'createdBy:id,name',
            ]),
            $query,
            $viewer,
        )->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function visibleTo(User $viewer): Builder
    {
        $query = PettyCashFloat::query();

        if ($viewer->hasAnyRole(['admin', 'bendahara', 'asisten_bendahara', 'auditor', 'ketua'])) {
            return $query;
        }

        return $query->where('custodian_user_id', $viewer->getKey());
    }

    private function applyFilters(Builder $builder, ListQueryDto $query, User $viewer): Builder
    {
        return ListQueryApplier::apply(
            $builder,
            $query,
            searchColumns: ['float_number', 'title', 'notes'],
            sortable: ['created_at', 'float_number', 'status', 'purpose', 'period_start'],
            defaultSort: 'created_at',
            filterCallbacks: [
                'status' => function (Builder $q, mixed $v): void {
                    $statuses = is_array($v) ? $v : explode(',', (string) $v);
                    $q->whereIn('status', array_values(array_filter(array_map('trim', $statuses))));
                },
                'purpose' => fn (Builder $q, mixed $v) => $q->where('purpose', $v),
                'custodian_user_id' => fn (Builder $q, mixed $v) => $q->where('custodian_user_id', (int) $v),
                'program_id' => fn (Builder $q, mixed $v) => $q->where('program_id', (int) $v),
                'mine' => function (Builder $q, mixed $v) use ($viewer): void {
                    if (filter_var($v, FILTER_VALIDATE_BOOLEAN)) {
                        $q->where('custodian_user_id', $viewer->getKey());
                    }
                },
            ],
        );
    }
}
