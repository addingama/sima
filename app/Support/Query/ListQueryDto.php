<?php

namespace App\Support\Query;

readonly class ListQueryDto
{
    /** @param  array<string, mixed>  $filters */
    public function __construct(
        public ?string $q = null,
        public ?string $sort = null,
        public string $direction = 'desc',
        public int $perPage = 15,
        public int $page = 1,
        public ?string $cursor = null,
        public array $filters = [],
    ) {}

    /** @param  array<string, mixed>  $validated */
    public static function fromValidated(array $validated, int $defaultPerPage = 15): self
    {
        $filters = $validated;
        unset($filters['q'], $filters['sort'], $filters['direction'], $filters['per_page'], $filters['page'], $filters['cursor']);

        return new self(
            q: $validated['q'] ?? null,
            sort: $validated['sort'] ?? null,
            direction: $validated['direction'] ?? 'desc',
            perPage: (int) ($validated['per_page'] ?? $defaultPerPage),
            page: (int) ($validated['page'] ?? 1),
            cursor: $validated['cursor'] ?? null,
            filters: $filters,
        );
    }
}
