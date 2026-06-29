<?php

namespace App\Http\Controllers\Api;

use App\Domains\Reconciliation\Services\ReconciliationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reconciliation\AddBankReconciliationLineRequest;
use App\Http\Requests\Reconciliation\CompleteBankReconciliationRequest;
use App\Http\Requests\Reconciliation\ListBankReconciliationRequest;
use App\Http\Requests\Reconciliation\StoreBankReconciliationRequest;
use App\Http\Resources\BankReconciliationLineResource;
use App\Http\Resources\BankReconciliationResource;
use App\Models\BankReconciliation;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly ReconciliationService $service) {}

    #[OA\Get(
        path: '/bank-reconciliations',
        summary: 'Daftar rekonsiliasi bank',
        tags: ['BankReconciliation'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListBankReconciliationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', BankReconciliation::class);

        return $this->collection(BankReconciliationResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/bank-reconciliations',
        summary: 'Buat rekonsiliasi bank',
        tags: ['BankReconciliation'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreBankReconciliationRequest $request): JsonResponse
    {
        $reconciliation = $this->service->create($request->validated(), $request->user());

        return $this->created(new BankReconciliationResource($reconciliation->load('account:id,code,name')));
    }

    #[OA\Get(
        path: '/bank-reconciliations/{bankReconciliation}',
        summary: 'Detail rekonsiliasi bank',
        tags: ['BankReconciliation'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(BankReconciliation $bankReconciliation): JsonResponse
    {
        $this->authorize('view', $bankReconciliation);

        return $this->resource(new BankReconciliationResource($this->service->showDetail($bankReconciliation)));
    }

    #[OA\Post(
        path: '/bank-reconciliations/{bankReconciliation}/lines',
        summary: 'Tambah baris rekonsiliasi',
        tags: ['BankReconciliation'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function addLine(AddBankReconciliationLineRequest $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        $line = $this->service->addLine($bankReconciliation, $request->validated());

        return $this->created(new BankReconciliationLineResource($line));
    }

    #[OA\Post(
        path: '/bank-reconciliations/{bankReconciliation}/complete',
        summary: 'Selesaikan rekonsiliasi bank',
        tags: ['BankReconciliation'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function complete(CompleteBankReconciliationRequest $request, BankReconciliation $bankReconciliation): JsonResponse
    {
        return $this->resource(new BankReconciliationResource(
            $this->service->complete($bankReconciliation, $request->user())->load('account:id,code,name')
        ));
    }
}
