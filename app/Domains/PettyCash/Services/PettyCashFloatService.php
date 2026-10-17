<?php

namespace App\Domains\PettyCash\Services;

use App\Domains\PettyCash\Repositories\PettyCashFloatRepository;
use App\Enums\PettyCashFloatPurpose;
use App\Enums\PettyCashFloatStatus;
use App\Models\PettyCashFloat;
use App\Models\PettyCashLine;
use App\Models\User;
use App\Services\DocumentNumberService;
use App\Support\Query\ListQueryDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PettyCashFloatService
{
    public function __construct(
        private readonly PettyCashFloatRepository $repository,
        private readonly DocumentNumberService $numbers,
        private readonly PettyCashCategoryService $categories,
    ) {}

    public function paginate(ListQueryDto $query, User $viewer): LengthAwarePaginator
    {
        return $this->repository->paginate($query, $viewer);
    }

    public function findForShow(PettyCashFloat $float): PettyCashFloat
    {
        return $float->load([
            'custodian:id,name,email',
            'program:id,code,name',
            'createdBy:id,name',
            'lines.category:id,code,name',
            'fundings.disbursement:id,disbursement_number,status,amount,disbursement_date',
            'topupRequests',
        ]);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $actor): PettyCashFloat
    {
        $this->validatePurposeFields($data);

        return DB::transaction(function () use ($data, $actor): PettyCashFloat {
            return PettyCashFloat::create([
                'float_number' => $this->numbers->next('KSK'),
                'title' => $data['title'],
                'purpose' => $data['purpose'],
                'status' => PettyCashFloatStatus::DRAFT,
                'custodian_user_id' => $data['custodian_user_id'],
                'program_id' => $data['program_id'] ?? null,
                'period_start' => $data['period_start'] ?? null,
                'period_end' => $data['period_end'] ?? null,
                'notes' => $data['notes'] ?? null,
                'returned_amount' => '0.00',
                'created_by' => $actor->id,
            ])->load(['custodian:id,name', 'program:id,code,name', 'createdBy:id,name']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(PettyCashFloat $float, array $data, User $actor): PettyCashFloat
    {
        if ($float->status === PettyCashFloatStatus::CLOSED || $float->status === PettyCashFloatStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Float yang sudah ditutup/dibatalkan tidak dapat diubah.'],
            ]);
        }

        $merged = array_merge([
            'purpose' => $float->purpose->value,
            'period_start' => $float->period_start?->toDateString(),
            'period_end' => $float->period_end?->toDateString(),
        ], $data);
        $this->validatePurposeFields($merged);

        return DB::transaction(function () use ($float, $data): PettyCashFloat {
            $float->update(collect($data)->only([
                'title', 'purpose', 'custodian_user_id', 'program_id',
                'period_start', 'period_end', 'notes',
            ])->all());

            return $this->findForShow($float->refresh());
        });
    }

    public function cancel(PettyCashFloat $float): PettyCashFloat
    {
        if ($float->lines()->exists()) {
            throw ValidationException::withMessages([
                'status' => ['Float yang sudah punya baris belanja tidak dapat dibatalkan. Tutup atau hapus baris terlebih dahulu.'],
            ]);
        }

        $float->update(['status' => PettyCashFloatStatus::CANCELLED]);

        return $this->findForShow($float->refresh());
    }

    /** @param  array<string, mixed>  $data */
    public function addLine(PettyCashFloat $float, array $data, User $actor): PettyCashLine
    {
        $this->assertReporting($float);
        $this->categories->assertActive((int) $data['category_id']);
        $this->assertAmount((string) $data['amount']);

        return DB::transaction(function () use ($float, $data, $actor): PettyCashLine {
            $line = PettyCashLine::create([
                'petty_cash_float_id' => $float->id,
                'category_id' => $data['category_id'],
                'spent_on' => $data['spent_on'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'created_by' => $actor->id,
            ]);

            return $line->load('category:id,code,name');
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateLine(PettyCashFloat $float, PettyCashLine $line, array $data): PettyCashLine
    {
        $this->assertReporting($float);
        $this->assertLineBelongs($float, $line);

        if (isset($data['category_id'])) {
            $this->categories->assertActive((int) $data['category_id']);
        }
        if (isset($data['amount'])) {
            $this->assertAmount((string) $data['amount']);
        }

        return DB::transaction(function () use ($line, $data): PettyCashLine {
            $line->update(collect($data)->only([
                'category_id', 'spent_on', 'amount', 'description',
            ])->all());

            return $line->refresh()->load('category:id,code,name');
        });
    }

    public function deleteLine(PettyCashFloat $float, PettyCashLine $line): void
    {
        $this->assertReporting($float);
        $this->assertLineBelongs($float, $line);
        $line->delete();
    }

    /** @param  array<string, mixed>  $data */
    private function validatePurposeFields(array $data): void
    {
        $purpose = $data['purpose'] ?? null;
        if ($purpose === PettyCashFloatPurpose::OPERATIONAL->value
            || $purpose === PettyCashFloatPurpose::OPERATIONAL) {
            if (empty($data['period_start']) || empty($data['period_end'])) {
                throw ValidationException::withMessages([
                    'period_start' => ['Periode wajib diisi untuk float operasional.'],
                    'period_end' => ['Periode wajib diisi untuk float operasional.'],
                ]);
            }
            if ($data['period_end'] < $data['period_start']) {
                throw ValidationException::withMessages([
                    'period_end' => ['Tanggal akhir periode harus sama atau setelah tanggal mulai.'],
                ]);
            }
        }
    }

    private function assertReporting(PettyCashFloat $float): void
    {
        if ($float->status !== PettyCashFloatStatus::REPORTING) {
            throw ValidationException::withMessages([
                'status' => ['Baris belanja hanya bisa diubah saat float berstatus reporting.'],
            ]);
        }
    }

    private function assertLineBelongs(PettyCashFloat $float, PettyCashLine $line): void
    {
        if ((int) $line->petty_cash_float_id !== (int) $float->id) {
            throw ValidationException::withMessages([
                'line' => ['Baris tidak termasuk float ini.'],
            ]);
        }
    }

    private function assertAmount(string $amount): void
    {
        if (bccomp($amount, '0', 2) !== 1) {
            throw ValidationException::withMessages([
                'amount' => ['Nominal harus lebih dari 0.'],
            ]);
        }
    }
}
