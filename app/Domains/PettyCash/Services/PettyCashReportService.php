<?php

namespace App\Domains\PettyCash\Services;

use App\Enums\DisbursementStatus;
use App\Models\PettyCashFloat;
use App\Models\PettyCashFunding;
use App\Models\PettyCashLine;
use Illuminate\Support\Collection;

class PettyCashReportService
{
    /**
     * @param  array{from?: string|null, to?: string|null, purpose?: string|null, status?: string|null, custodian_user_id?: int|null, program_id?: int|null}  $filters
     * @return array{summary: array<string, int|string>, rows: list<array<string, mixed>>}
     */
    public function floats(array $filters = []): array
    {
        $query = PettyCashFloat::query()
            ->with([
                'custodian:id,name',
                'program:id,code,name',
                'fundings.disbursement:id,status,amount',
                'lines:id,petty_cash_float_id,amount,category_id',
                'lines.category:id,code,name',
            ])
            ->orderByDesc('created_at');

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['purpose'])) {
            $query->where('purpose', $filters['purpose']);
        }
        if (! empty($filters['status'])) {
            $statuses = is_array($filters['status'])
                ? $filters['status']
                : explode(',', (string) $filters['status']);
            $query->whereIn('status', array_filter(array_map('trim', $statuses)));
        }
        if (! empty($filters['custodian_user_id'])) {
            $query->where('custodian_user_id', (int) $filters['custodian_user_id']);
        }
        if (! empty($filters['program_id'])) {
            $query->where('program_id', (int) $filters['program_id']);
        }

        /** @var Collection<int, PettyCashFloat> $floats */
        $floats = $query->get();

        $fundedTotal = '0.00';
        $spentTotal = '0.00';
        $returnedTotal = '0.00';

        $rows = [];
        foreach ($floats as $float) {
            $funded = $this->fundedFor($float);
            $spent = $this->spentFor($float);
            $returned = bcadd((string) $float->returned_amount, '0', 2);
            $outstanding = bcsub(bcsub($funded, $spent, 2), $returned, 2);

            $fundedTotal = bcadd($fundedTotal, $funded, 2);
            $spentTotal = bcadd($spentTotal, $spent, 2);
            $returnedTotal = bcadd($returnedTotal, $returned, 2);

            $topupCount = $float->fundings->filter(fn ($f) => $f->kind?->value === 'topup')->count();

            $rows[] = [
                'id' => $float->id,
                'float_number' => $float->float_number,
                'title' => $float->title,
                'purpose' => $float->purpose?->value,
                'purpose_label' => $float->purpose === null ? '-' : (
                    $float->purpose->value === 'operational' ? 'Operasional' : 'Event'
                ),
                'status' => $float->status?->value,
                'custodian_name' => $float->custodian?->name,
                'program_name' => $float->program?->name,
                'period_start' => $float->period_start?->toDateString(),
                'period_end' => $float->period_end?->toDateString(),
                'funded_total' => $funded,
                'spent_total' => $spent,
                'returned_total' => $returned,
                'outstanding_total' => $outstanding,
                'topup_count' => $topupCount,
                'category_breakdown' => $this->categoryBreakdown($float),
            ];
        }

        return [
            'summary' => [
                'float_count' => count($rows),
                'funded_total' => $fundedTotal,
                'spent_total' => $spentTotal,
                'returned_total' => $returnedTotal,
                'outstanding_total' => bcsub(bcsub($fundedTotal, $spentTotal, 2), $returnedTotal, 2),
            ],
            'rows' => $rows,
        ];
    }

    private function fundedFor(PettyCashFloat $float): string
    {
        $total = '0.00';
        foreach ($float->fundings as $funding) {
            /** @var PettyCashFunding $funding */
            if ($funding->disbursement?->status !== DisbursementStatus::APPROVED) {
                continue;
            }
            $total = bcadd($total, (string) $funding->amount, 2);
        }

        return $total;
    }

    private function spentFor(PettyCashFloat $float): string
    {
        $total = '0.00';
        foreach ($float->lines as $line) {
            /** @var PettyCashLine $line */
            $total = bcadd($total, (string) $line->amount, 2);
        }

        return $total;
    }

    /** @return list<array{category: string, amount: string}> */
    private function categoryBreakdown(PettyCashFloat $float): array
    {
        $map = [];
        foreach ($float->lines as $line) {
            $key = $line->category?->name ?? 'Tanpa kategori';
            $map[$key] = bcadd($map[$key] ?? '0.00', (string) $line->amount, 2);
        }

        $out = [];
        foreach ($map as $category => $amount) {
            $out[] = ['category' => $category, 'amount' => $amount];
        }

        return $out;
    }
}
