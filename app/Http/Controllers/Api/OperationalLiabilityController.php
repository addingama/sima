<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Models\OperationalLiability;
use App\Services\DocumentNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Register kewajiban operasional (utang belum dibayar). Bersifat pencatatan komitmen;
 * arus kas terjadi melalui Pengeluaran (disbursement) yang menyelesaikannya.
 */
class OperationalLiabilityController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function index(Request $request): JsonResponse
    {
        $items = OperationalLiability::query()
            ->with(['fund:id,code,name', 'program:id,code,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->integer('fund_id')))
            ->orderByDesc('liability_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'liability_date' => ['required', 'date'],
            'creditor' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fund_id' => ['nullable', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $data['liability_number'] = $this->numbers->next('LIB');
        $data['status'] = 'outstanding';
        $data['amount_settled'] = 0;
        $data['created_by'] = $request->user()->id;

        $liability = OperationalLiability::create($data);

        return response()->json($liability, 201);
    }

    public function show(OperationalLiability $operationalLiability): JsonResponse
    {
        return response()->json($operationalLiability->load([
            'fund:id,code,name', 'program:id,code,name', 'settledDisbursement', 'attachments',
        ]));
    }

    public function update(Request $request, OperationalLiability $operationalLiability): JsonResponse
    {
        if ($operationalLiability->status !== 'outstanding') {
            throw new DomainException('Hanya liabilitas berstatus outstanding yang dapat diubah.');
        }

        $data = $request->validate([
            'liability_date' => ['sometimes', 'date'],
            'creditor' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fund_id' => ['nullable', 'exists:funds,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'amount' => ['sometimes', 'numeric', 'gt:0'],
            'due_date' => ['nullable', 'date'],
        ]);

        $operationalLiability->update($data);

        return response()->json($operationalLiability);
    }

    /** Menyelesaikan liabilitas dengan menautkan Pengeluaran yang sudah di-approve. */
    public function settle(Request $request, OperationalLiability $operationalLiability): JsonResponse
    {
        if (in_array($operationalLiability->status, ['settled', 'void'], true)) {
            throw new DomainException('Liabilitas sudah selesai atau dibatalkan.');
        }

        $data = $request->validate([
            'disbursement_id' => ['required', 'exists:disbursements,id'],
        ]);

        return DB::transaction(function () use ($operationalLiability, $data): JsonResponse {
            $disbursement = Disbursement::findOrFail($data['disbursement_id']);

            if ($disbursement->status->value !== 'approved') {
                throw new DomainException('Pengeluaran penyelesai harus berstatus approved (sudah ter-post).');
            }

            $newSettled = bcadd((string) $operationalLiability->amount_settled, (string) $disbursement->amount, 2);
            $status = bccomp($newSettled, (string) $operationalLiability->amount, 2) >= 0
                ? 'settled'
                : 'partially_settled';

            $operationalLiability->update([
                'amount_settled' => $newSettled,
                'status' => $status,
                'settled_disbursement_id' => $disbursement->id,
                'settled_at' => $status === 'settled' ? now() : null,
            ]);

            return response()->json($operationalLiability->refresh());
        });
    }

    public function void(Request $request, OperationalLiability $operationalLiability): JsonResponse
    {
        if ($operationalLiability->status === 'void') {
            throw new DomainException('Liabilitas sudah dibatalkan.');
        }

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $operationalLiability->update([
            'status' => 'void',
            'voided_at' => now(),
            'voided_by' => $request->user()->id,
            'void_reason' => $data['reason'],
        ]);

        return response()->json($operationalLiability);
    }
}
