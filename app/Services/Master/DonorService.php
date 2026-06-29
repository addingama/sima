<?php

namespace App\Services\Master;

use App\Models\Donor;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DonorService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Donor
    {
        return Donor::create([...$data, 'created_by' => $actor->id]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Donor $donor, array $data): Donor
    {
        $donor->update($data);

        return $donor->refresh();
    }

    public function delete(Donor $donor): void
    {
        $donor->delete();
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Donor::query(),
            $query,
            searchColumns: ['name', 'code', 'email', 'phone'],
            sortable: ['name', 'code', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
