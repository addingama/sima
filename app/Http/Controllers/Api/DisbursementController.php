<?php

namespace App\Http\Controllers\Api;

use App\Domains\Expense\Services\ExpenseReversalService;
use App\Domains\Expense\Services\ExpenseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disbursement\ApproveDisbursementRequest;
use App\Http\Requests\Disbursement\RejectDisbursementRequest;
use App\Http\Requests\Disbursement\ReverseDisbursementRequest;
use App\Http\Requests\Disbursement\StoreDisbursementRequest;
use App\Http\Requests\Disbursement\UpdateDisbursementRequest;
use App\Http\Requests\Disbursement\VerifyDisbursementRequest;
use App\Http\Resources\DisbursementResource;
use App\Models\Disbursement;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    public function __construct(
        private readonly ExpenseService $service,
        private readonly ExpenseReversalService $reversal,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Disbursement::class);

        $items = $this->service->paginate([
            'status' => $request->filled('status') ? $request->string('status')->value() : null,
            'fund_id' => $request->filled('fund_id') ? $request->integer('fund_id') : null,
            'program_id' => $request->filled('program_id') ? $request->integer('program_id') : null,
            'from' => $request->filled('from') ? $request->date('from') : null,
            'to' => $request->filled('to') ? $request->date('to') : null,
        ], $request->integer('per_page', 15));

        return DisbursementResource::collection($items)->response();
    }

    public function store(StoreDisbursementRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $disbursement = $this->service->create(
                $request->expenseData(),
                $request->sources(),
                $request->user(),
            );

            return (new DisbursementResource($disbursement->load('fundSources.fund', 'fundSources.program')))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function show(Disbursement $disbursement): JsonResponse
    {
        $this->authorize('view', $disbursement);

        return (new DisbursementResource($disbursement->load([
            'account:id,code,name',
            'program:id,code,name',
            'fundSources.fund:id,code,name',
            'fundSources.program:id,code,name',
            'approvals.actor:id,name',
            'attachments',
        ])))->response();
    }

    public function update(UpdateDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        $disbursement = $this->service->update(
            $disbursement,
            $request->expenseData(),
            $request->sources(),
            $request->user(),
        );

        return (new DisbursementResource($disbursement->load('fundSources.fund', 'fundSources.program')))->response();
    }

    public function submit(Disbursement $disbursement, Request $request): JsonResponse
    {
        $this->authorize('submit', $disbursement);

        return (new DisbursementResource($this->service->submit($disbursement, $request->user())))->response();
    }

    public function verify(VerifyDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return (new DisbursementResource(
            $this->service->verify($disbursement, $request->user(), $request->validated('notes'))
        ))->response();
    }

    public function approve(ApproveDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $disbursement): JsonResponse {
            return (new DisbursementResource(
                $this->service->approve($disbursement, $request->user(), $request->validated('notes'))
            ))->response();
        });
    }

    public function reject(RejectDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return (new DisbursementResource(
            $this->service->reject($disbursement, $request->user(), $request->validated('reason'))
        ))->response();
    }

    public function reverse(ReverseDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $disbursement): JsonResponse {
            return (new DisbursementResource(
                $this->reversal->reverse($disbursement, $request->user(), $request->validated('reason'))
            ))->response();
        });
    }
}
