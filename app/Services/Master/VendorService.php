<?php

namespace App\Services\Master;

use App\Models\User;
use App\Models\Vendor;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VendorService
{
    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Vendor
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->documentNumbers->next('VND');
        }

        unset($data['code']);

        return Vendor::create([
            ...$data,
            'code' => $code,
            'created_by' => $actor->id,
        ]);
    }

    /** @param array<string, mixed> $data */
    public function update(Vendor $vendor, array $data): Vendor
    {
        unset($data['code']);

        $vendor->update($data);

        return $vendor->refresh();
    }

    public function delete(Vendor $vendor): void
    {
        $vendor->delete();
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Vendor::query(),
            $query,
            searchColumns: ['name', 'code', 'email', 'phone', 'contact_name'],
            sortable: ['name', 'code', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
