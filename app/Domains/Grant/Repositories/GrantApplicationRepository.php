<?php

namespace App\Domains\Grant\Repositories;

use App\Enums\GrantApplicationStatus;
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
        return $this->applyListFilters(
            $this->visibleTo($viewer)->with([
                'assignedVerifier:id,name',
                'program:id,code,name',
                'createdBy:id,name',
                'handedOverBy:id,name',
            ]),
            $query,
            $viewer,
        )->paginate($query->perPage, ['*'], 'page', $query->page);
    }

    /** @return array<string, int|string> */
    public function summarize(ListQueryDto $query, User $viewer): array
    {
        $rows = $this->applyListFilters($this->visibleTo($viewer), $query, $viewer)
            ->reorder()
            ->selectRaw(
                'status,
                COUNT(*) as cnt,
                COALESCE(SUM(recommended_amount), 0) as recommended_sum,
                COALESCE(SUM(approved_amount), 0) as approved_sum'
            )
            ->groupBy('status')
            ->get();

        $jumlahKartu = 0;
        $antrian = 0;
        $usulan = '0.00';
        $disetujui = '0.00';
        $siapDiserahkan = '0.00';
        $sudahDiserahkan = '0.00';
        $ditolak = '0.00';

        $queueStatuses = [
            GrantApplicationStatus::DRAFT->value,
            GrantApplicationStatus::VERIFICATION->value,
            GrantApplicationStatus::PENDING_APPROVAL->value,
        ];

        foreach ($rows as $row) {
            $status = $row->status instanceof GrantApplicationStatus
                ? $row->status->value
                : (string) $row->status;
            $count = (int) $row->cnt;
            $jumlahKartu += $count;
            $recommended = bcadd((string) $row->recommended_sum, '0', 2);
            $approved = bcadd((string) $row->approved_sum, '0', 2);
            $usulan = bcadd($usulan, $recommended, 2);

            if (in_array($status, $queueStatuses, true)) {
                $antrian += $count;
            }

            if ($status === GrantApplicationStatus::APPROVED->value) {
                $siapDiserahkan = bcadd($siapDiserahkan, $approved, 2);
                $disetujui = bcadd($disetujui, $approved, 2);
            }

            if ($status === GrantApplicationStatus::COMPLETED->value) {
                $sudahDiserahkan = bcadd($sudahDiserahkan, $approved, 2);
                $disetujui = bcadd($disetujui, $approved, 2);
            }

            if ($status === GrantApplicationStatus::REJECTED->value) {
                $ditolak = bcadd($ditolak, $recommended, 2);
            }
        }

        return [
            'jumlah_kartu' => $jumlahKartu,
            'antrian' => $antrian,
            'usulan' => $usulan,
            'disetujui' => $disetujui,
            'siap_diserahkan' => $siapDiserahkan,
            'sudah_diserahkan' => $sudahDiserahkan,
            'ditolak' => $ditolak,
        ];
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

    private function applyListFilters(Builder $builder, ListQueryDto $query, User $viewer): Builder
    {
        return ListQueryApplier::apply(
            $builder,
            $query,
            searchColumns: ['application_number', 'recipient_name', 'recommender_name', 'reason'],
            sortable: ['created_at', 'application_number', 'recommended_amount', 'status'],
            defaultSort: 'created_at',
            filterCallbacks: $this->filterCallbacks($viewer),
        );
    }

    /** @return array<string, callable(Builder, mixed): void> */
    private function filterCallbacks(User $viewer): array
    {
        return [
            'mine' => function (Builder $q, mixed $v) use ($viewer): void {
                if (filter_var($v, FILTER_VALIDATE_BOOLEAN)) {
                    $q->where(function (Builder $inner) use ($viewer): void {
                        $inner->where('created_by', $viewer->getKey())
                            ->orWhere('assigned_verifier_id', $viewer->getKey());
                    });
                }
            },
            'status' => function (Builder $q, mixed $v): void {
                $items = is_array($v) ? $v : explode(',', (string) $v);
                $statuses = array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $items,
                )));

                if ($statuses !== []) {
                    $q->whereIn('status', $statuses);
                }
            },
            'from' => function (Builder $q, mixed $v): void {
                $q->whereDate('created_at', '>=', (string) $v);
            },
            'to' => function (Builder $q, mixed $v): void {
                $q->whereDate('created_at', '<=', (string) $v);
            },
        ];
    }
}
