<?php

namespace App\Services\Master;

use App\Models\Program;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProgramService
{
    public function __construct(private readonly DocumentNumberService $documentNumbers) {}

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Program
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->documentNumbers->next('EVT');
        }

        unset($data['code']);

        return Program::create([
            ...$data,
            'code' => $code,
            'created_by' => $actor->id,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Program $program, array $data): Program
    {
        unset($data['code']);

        $program->update($data);

        return $program->refresh();
    }

    public function delete(Program $program): void
    {
        $program->delete();
    }

    public function findForShow(Program $program): Program
    {
        return $program->load('fund:id,code,name');
    }

    public function paginate(ListQueryDto $query): LengthAwarePaginator
    {
        $builder = ListQueryApplier::apply(
            Program::query()->with('fund:id,code,name'),
            $query,
            searchColumns: ['name', 'code'],
            sortable: ['id', 'name', 'code', 'event_type', 'start_date', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
