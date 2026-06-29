<?php

namespace App\Http\Controllers\Api;

use App\Domains\Expense\Services\ExpenseReversalService;
use App\Domains\Expense\Services\ExpenseService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disbursement\ApproveDisbursementRequest;
use App\Http\Requests\Disbursement\ListDisbursementRequest;
use App\Http\Requests\Disbursement\RejectDisbursementRequest;
use App\Http\Requests\Disbursement\ReverseDisbursementRequest;
use App\Http\Requests\Disbursement\StoreDisbursementRequest;
use App\Http\Requests\Disbursement\SubmitDisbursementRequest;
use App\Http\Requests\Disbursement\UpdateDisbursementRequest;
use App\Http\Requests\Disbursement\VerifyDisbursementRequest;
use App\Http\Resources\DisbursementResource;
use App\Models\Disbursement;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DisbursementController extends Controller
{
    public function __construct(
        private readonly ExpenseService $service,
        private readonly ExpenseReversalService $reversal,
        private readonly IdempotencyService $idempotency,
    ) {}

    #[OA\Get(
        path: '/disbursements',
        summary: 'Daftar pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListDisbursementRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Disbursement::class);

        return $this->collection(DisbursementResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/disbursements',
        summary: 'Buat pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreDisbursementRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $disbursement = $this->service->create(
                $request->expenseData(),
                $request->sources(),
                $request->user(),
            );

            return $this->created(new DisbursementResource($this->service->loadWithSources($disbursement)));
        });
    }

    #[OA\Get(
        path: '/disbursements/{disbursement}',
        summary: 'Detail pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Disbursement $disbursement): JsonResponse
    {
        $this->authorize('view', $disbursement);

        return $this->resource(new DisbursementResource($this->service->findForShow($disbursement)));
    }

    #[OA\Put(
        path: '/disbursements/{disbursement}',
        summary: 'Ubah pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        $disbursement = $this->service->update(
            $disbursement,
            $request->expenseData(),
            $request->sources(),
            $request->user(),
        );

        return $this->resource(new DisbursementResource($this->service->loadWithSources($disbursement)));
    }

    #[OA\Post(
        path: '/disbursements/{disbursement}/submit',
        summary: 'Ajukan pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function submit(SubmitDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        $this->authorize('submit', $disbursement);

        return $this->resource(new DisbursementResource($this->service->submit($disbursement, $request->user())));
    }

    #[OA\Post(
        path: '/disbursements/{disbursement}/verify',
        summary: 'Verifikasi pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function verify(VerifyDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return $this->resource(new DisbursementResource(
            $this->service->verify($disbursement, $request->user(), $request->validated('notes'))
        ));
    }

    #[OA\Post(
        path: '/disbursements/{disbursement}/approve',
        summary: 'Setujui pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function approve(ApproveDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $disbursement): JsonResponse {
            return $this->resource(new DisbursementResource(
                $this->service->approve($disbursement, $request->user(), $request->validated('notes'))
            ));
        });
    }

    #[OA\Post(
        path: '/disbursements/{disbursement}/reject',
        summary: 'Tolak pengeluaran',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reject(RejectDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return $this->resource(new DisbursementResource(
            $this->service->reject($disbursement, $request->user(), $request->validated('reason'))
        ));
    }

    #[OA\Post(
        path: '/disbursements/{disbursement}/reverse',
        summary: 'Batalkan pengeluaran (reversal)',
        tags: ['Disbursement'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reverse(ReverseDisbursementRequest $request, Disbursement $disbursement): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $disbursement): JsonResponse {
            return $this->resource(new DisbursementResource(
                $this->reversal->reverse($disbursement, $request->user(), $request->validated('reason'))
            ));
        });
    }
}
