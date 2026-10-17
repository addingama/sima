<?php

namespace App\Domains\PettyCash\Services;

use App\Models\PettyCashCategory;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashCategoryService
{
    public function paginate(ListQueryDto $query, bool $activeOnly = false): LengthAwarePaginator
    {
        $builder = PettyCashCategory::query()->orderBy('sort_order')->orderBy('name');

        if ($activeOnly) {
            $builder->where('is_active', true);
        }

        return ListQueryApplier::apply(
            $builder,
            $query,
            searchColumns: ['code', 'name', 'description'],
            sortable: ['code', 'name', 'sort_order', 'created_at'],
            defaultSort: 'sort_order',
            defaultDirection: 'asc',
            filterCallbacks: [
                'is_active' => fn ($q, $v) => $q->where('is_active', filter_var($v, FILTER_VALIDATE_BOOLEAN)),
            ],
        )->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): PettyCashCategory
    {
        return DB::transaction(fn () => PettyCashCategory::create([
            'code' => strtoupper((string) $data['code']),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]));
    }

    /** @param  array<string, mixed>  $data */
    public function update(PettyCashCategory $category, array $data): PettyCashCategory
    {
        return DB::transaction(function () use ($category, $data): PettyCashCategory {
            if (isset($data['code'])) {
                $data['code'] = strtoupper((string) $data['code']);
            }
            $category->update($data);

            return $category->refresh();
        });
    }

    public function deactivate(PettyCashCategory $category): PettyCashCategory
    {
        if (! $category->is_active) {
            return $category;
        }

        if ($category->lines()->exists()) {
            $category->update(['is_active' => false]);

            return $category->refresh();
        }

        $category->update(['is_active' => false]);

        return $category->refresh();
    }

    public function assertActive(int $categoryId): PettyCashCategory
    {
        $category = PettyCashCategory::query()->find($categoryId);
        if ($category === null || ! $category->is_active) {
            throw ValidationException::withMessages([
                'category_id' => ['Kategori belanja tidak aktif atau tidak ditemukan.'],
            ]);
        }

        return $category;
    }
}
