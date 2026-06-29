<?php

namespace App\Services\Master;

use App\Models\Program;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProgramService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): Program
    {
        return Program::create([...$data, 'created_by' => $actor->id]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Program $program, array $data): Program
    {
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
            sortable: ['name', 'code', 'start_date', 'created_at'],
            defaultSort: 'id',
            defaultDirection: 'desc',
        );

        return $builder->paginate($query->perPage, ['*'], 'page', $query->page);
    }
}
