<?php

namespace App\Support\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ListQueryApplier
{
    /**
     * @param  array<int, string>  $searchColumns
     * @param  array<int, string>  $sortable
     * @param  array<string, callable(Builder, mixed): void>  $filterCallbacks
     */
    public static function apply(
        Builder $query,
        ListQueryDto $dto,
        array $searchColumns = [],
        array $sortable = [],
        string $defaultSort = 'id',
        string $defaultDirection = 'desc',
        array $filterCallbacks = [],
    ): Builder {
        if ($dto->q !== null && $dto->q !== '' && $searchColumns !== []) {
            $term = '%'.$dto->q.'%';
            $query->where(function (Builder $w) use ($searchColumns, $term): void {
                foreach ($searchColumns as $column) {
                    $w->orWhere($column, 'like', $term);
                }
            });
        }

        foreach ($dto->filters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (isset($filterCallbacks[$key])) {
                $filterCallbacks[$key]($query, $value);

                continue;
            }

            if (is_bool($value) || in_array($value, ['0', '1', 'true', 'false'], true)) {
                $query->where($key, filter_var($value, FILTER_VALIDATE_BOOLEAN));

                continue;
            }

            $query->where($key, $value);
        }

        $sort = $dto->sort ?? $defaultSort;
        $direction = strtolower($dto->direction) === 'asc' ? 'asc' : 'desc';

        if ($sortable !== [] && ! in_array($sort, $sortable, true)) {
            throw ValidationException::withMessages([
                'sort' => ["Kolom sort tidak valid. Pilihan: ".implode(', ', $sortable)],
            ]);
        }

        return $query->orderBy($sort, $direction);
    }
}
