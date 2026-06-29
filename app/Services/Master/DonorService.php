<?php

namespace App\Services\Master;

use App\Models\Donor;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DonorService
{
    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Donor
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->documentNumbers->next('DON');
        }

        unset($data['code']);

        return Donor::create([
            ...$data,
            'code' => $code,
            'created_by' => $actor->id,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Donor $donor, array $data): Donor
    {
        unset($data['code']);

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
