<?php

namespace App\Http\Controllers\Api;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Reconciliation\Services\ReconciliationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reconciliation\AddBankReconciliationLineRequest;
use App\Http\Requests\Reconciliation\CompleteBankReconciliationRequest;
use App\Http\Requests\Reconciliation\StoreBankReconciliationRequest;
use App\Models\BankReconciliation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankReconciliation::class);

        $items = $this->service->paginate([
            'account_id' => $request->filled('account_id') ? $request->integer('account_id') : null,
            'status' => $request->filled('status') ? $request->string('status')->value() : null,
        ], $request->integer('per_page', 15));

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
