<?php

namespace App\Domains\Grant\Repositories;

use App\Models\GrantApplication;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GrantApplicationRepository
{
    public function paginate(ListQueryDto $query, User $viewer): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            $this->visibleTo($viewer)->with([
                'assignedVerifier:id,name',
                'program:id,code,name',
                'createdBy:id,name',
            ]),
            $query,
            searchColumns: ['application_number', 'recipient_name', 'recommender_name', 'reason'],
            sortable: ['created_at', 'application_number', 'recommended_amount', 'status'],
            defaultSort: 'created_at',
            filterCallbacks: [
                'mine' => function (Builder $q, mixed $v) use ($viewer): void {
                    if (filter_var($v, FILTER_VALIDATE_BOOLEAN)) {
                        $q->where(function (Builder $inner) use ($viewer): void {
                            $inner->where('created_by', $viewer->getKey())
                                ->orWhere('assigned_verifier_id', $viewer->getKey());
                        });
                    }
                },
            ],
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    public function visibleTo(User $viewer): Builder
    {
        $query = GrantApplication::query();

        if ($this->seesAll($viewer)) {
            return $query;
        }

        return $query->where(function (Builder $w) use ($viewer): void {
            $w->where('created_by', $viewer->getKey())
                ->orWhere('assigned_verifier_id', $viewer->getKey());
        });
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): GrantApplication
    {
        return GrantApplication::create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(GrantApplication $grant, array $data): GrantApplication
    {
        $grant->update($data);

        return $grant;
    }

    public function seesAll(User $viewer): bool
    {
        return $viewer->hasAnyRole(['admin', 'ketua', 'bendahara', 'auditor']);
    }
}
