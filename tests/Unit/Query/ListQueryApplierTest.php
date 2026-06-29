<?php

namespace Tests\Unit\Query;

use App\Models\Donor;
use App\Models\User;
use App\Support\Query\ListQueryApplier;
use App\Support\Query\ListQueryDto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListQueryApplierTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = User::factory()->create();
    }

    #[Test]
    public function it_applies_search_filter_and_sort(): void
    {
        Donor::create(['code' => 'DNR-A', 'name' => 'Ahmad Zakat', 'type' => 'individu', 'is_active' => true, 'created_by' => $this->actor->id]);
        Donor::create(['code' => 'DNR-B', 'name' => 'Budi Infak', 'type' => 'individu', 'is_active' => true, 'created_by' => $this->actor->id]);

        $dto = new ListQueryDto(q: 'Ahmad', page: 1, perPage: 15, sort: 'name', direction: 'asc', filters: ['is_active' => true]);
        $query = ListQueryApplier::apply(
            Donor::query(),
            $dto,
            searchColumns: ['code', 'name'],
            sortable: ['name', 'code'],
            defaultSort: 'id',
            filterCallbacks: [],
        );

        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertSame('Ahmad Zakat', $results->first()->name);
    }

    #[Test]
    public function it_rejects_invalid_sort_column(): void
    {
        $dto = new ListQueryDto(q: null, page: 1, perPage: 15, sort: 'invalid', direction: 'desc', filters: []);

        $this->expectException(ValidationException::class);

        ListQueryApplier::apply(
            Donor::query(),
            $dto,
            searchColumns: ['name'],
            sortable: ['name', 'code'],
        );
    }
}
