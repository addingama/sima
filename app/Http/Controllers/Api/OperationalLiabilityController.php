<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Liability\ListOperationalLiabilityRequest;
use App\Http\Requests\Liability\SettleOperationalLiabilityRequest;
use App\Http\Requests\Liability\StoreOperationalLiabilityRequest;
use App\Http\Requests\Liability\UpdateOperationalLiabilityRequest;
use App\Http\Requests\Liability\VoidOperationalLiabilityRequest;
use App\Http\Resources\OperationalLiabilityResource;
use App\Models\OperationalLiability;
use App\Services\OperationalLiabilityService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class OperationalLiabilityController extends Controller
{
    public function __construct(private readonly OperationalLiabilityService $service) {}

    #[OA\Get(
        path: '/liabilities',
        summary: 'Daftar kewajiban operasional',
        tags: ['OperationalLiability'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListOperationalLiabilityRequest $request): JsonResponse
    {
        $this->authorize('viewAny', OperationalLiability::class);

        return $this->collection(OperationalLiabilityResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/liabilities',
        summary: 'Buat kewajiban operasional',
        tags: ['OperationalLiability'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreOperationalLiabilityRequest $request): JsonResponse
    {
        $liability = $this->service->create($request->validated(), $request->user());

        return $this->created(new OperationalLiabilityResource($liability->load(['fund:id,code,name', 'program:id,code,name'])));
    }

    #[OA\Get(
        path: '/liabilities/{operationalLiability}',
        summary: 'Detail kewajiban operasional',
        tags: ['OperationalLiability'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(OperationalLiability $operationalLiability): JsonResponse
    {
        $this->authorize('view', $operationalLiability);

        return $this->resource(new OperationalLiabilityResource($this->service->findForShow($operationalLiability)));
    }

    #[OA\Put(
        path: '/liabilities/{operationalLiability}',
        summary: 'Ubah kewajiban operasional',
        tags: ['OperationalLiability'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateOperationalLiabilityRequest $request, OperationalLiability $operationalLiability): JsonResponse
    {
        return $this->resource(new OperationalLiabilityResource(
            $this->service->update($operationalLiability, $request->validated(), $request->user())
                ->load(['fund:id,code,name', 'program:id,code,name'])
        ));
    }

    #[OA\Post(
        path: '/liabilities/{operationalLiability}/settle',
        summary: 'Selesaikan kewajiban operasional',
        tags: ['OperationalLiability'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function settle(SettleOperationalLiabilityRequest $request, OperationalLiability $operationalLiability): JsonResponse
    {
        return $this->resource(new OperationalLiabilityResource(
            $this->service->settle($operationalLiability, $request->integer('disbursement_id'), $request->user())
                ->load(['fund:id,code,name', 'program:id,code,name', 'settledDisbursement'])
        ));
    }

    #[OA\Post(
        path: '/liabilities/{operationalLiability}/void',
        summary: 'Batalkan kewajiban operasional',
        tags: ['OperationalLiability'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function void(VoidOperationalLiabilityRequest $request, OperationalLiability $operationalLiability): JsonResponse
    {
        return $this->resource(new OperationalLiabilityResource(
            $this->service->void($operationalLiability, $request->validated('reason'), $request->user())
        ));
    }
}
