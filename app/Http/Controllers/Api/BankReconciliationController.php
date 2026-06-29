<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reconciliation\AddBankReconciliationLineRequest;
use App\Http\Requests\Reconciliation\CompleteBankReconciliationRequest;
use App\Http\Requests\Reconciliation\StoreBankReconciliationRequest;
use App\Models\BankReconciliation;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankReconciliation::class);

        $items = BankReconciliation::query()
            ->with('account:id,code,name')
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('period_end')
            ->paginate($request->integer('per_page', 15));

        return response()->json($items);
    }

    public function store(StoreBankReconciliationRequest $request): JsonResponse
    {
        $reconciliation = $this->service->create($request->validated(), $request->user());

        return response()->json($reconciliation->load('account:id,code,name'), 201);
    }

    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        $this->authorize('view', $bankReconciliation);

        $bankReconciliation->load([
            'account:id,code,name',
            'lines.ledgerEntry:id,transaction_type,debit,credit,reference,created_at',
        ]);

        $reconciling = $this->service->deferredBankFeeItems(
            $bankReconciliation->account_id,
            $bankReconciliation->period_end->toDateString()
        );

        return response()->json([
            ...$bankReconciliation->toArray(),
            'reconciling_items' => $reconciling['items'],
            'reconciling_total' => $reconciling['total'],
            'adjusted_difference' => $this->service->adjustedDifference(
                (string) $bankReconciliation->difference,
                $reconciling['total']
            ),
        ]);
    }

    public function addLine(AddBankReconciliationLineRequest $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $line = $this->service->addLine($bankReconciliation, $request->validated());

        return response()->json($line, 201);
    }

    public function complete(CompleteBankReconciliationRequest $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        return response()->json($this->service->complete($bankReconciliation, $request->user()));
    }
}
