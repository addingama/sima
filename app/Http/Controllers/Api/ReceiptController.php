<?php

namespace App\Http\Controllers\Api;

use App\Domains\Receipt\Services\ReceiptReversalService;
use App\Domains\Receipt\Services\ReceiptService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Receipt\ApproveReceiptRequest;
use App\Http\Requests\Receipt\ListReceiptRequest;
use App\Http\Requests\Receipt\RejectReceiptRequest;
use App\Http\Requests\Receipt\ReverseReceiptRequest;
use App\Http\Requests\Receipt\StoreReceiptRequest;
use App\Http\Requests\Receipt\SubmitReceiptRequest;
use App\Http\Requests\Receipt\UpdateReceiptRequest;
use App\Http\Resources\ReceiptAllocationResource;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Services\IdempotencyService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $service,
        private readonly ReceiptReversalService $reversal,
        private readonly IdempotencyService $idempotency,
    ) {}

    #[OA\Get(
        path: '/receipts',
        summary: 'Daftar penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListReceiptRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Receipt::class);

        return $this->collection(ReceiptResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/receipts',
        summary: 'Buat penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreReceiptRequest $request): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request): JsonResponse {
            $receipt = $this->service->create(
                $request->receiptData(),
                $request->allocations(),
                $request->user(),
            );

            return $this->created(new ReceiptResource($this->service->loadWithAllocations($receipt)));
        });
    }

    #[OA\Get(
        path: '/receipts/{receipt}',
        summary: 'Detail penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        return $this->resource(new ReceiptResource($this->service->findForShow($receipt)));
    }

    #[OA\Put(
        path: '/receipts/{receipt}',
        summary: 'Ubah penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        $receipt = $this->service->update(
            $receipt,
            $request->receiptData(),
            $request->allocations(),
            $request->user(),
        );

        return $this->resource(new ReceiptResource($this->service->loadWithAllocations($receipt)));
    }

    #[OA\Get(
        path: '/receipts/{receipt}/allocations',
        summary: 'Alokasi penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function allocations(Receipt $receipt): JsonResponse
    {
        $this->authorize('view', $receipt);

        return $this->collection(ReceiptAllocationResource::collection($this->service->allocationsFor($receipt)));
    }

    #[OA\Post(
        path: '/receipts/{receipt}/submit',
        summary: 'Ajukan penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function submit(SubmitReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        $this->authorize('submit', $receipt);

        return $this->resource(new ReceiptResource($this->service->submit($receipt, $request->user())));
    }

    #[OA\Post(
        path: '/receipts/{receipt}/approve',
        summary: 'Setujui penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function approve(ApproveReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $receipt): JsonResponse {
            return $this->resource(new ReceiptResource(
                $this->service->approve($receipt, $request->user(), $request->validated('notes'))
            ));
        });
    }

    #[OA\Post(
        path: '/receipts/{receipt}/reject',
        summary: 'Tolak penerimaan',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reject(RejectReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        return $this->resource(new ReceiptResource(
            $this->service->reject($receipt, $request->user(), $request->validated('reason'))
        ));
    }

    #[OA\Post(
        path: '/receipts/{receipt}/reverse',
        summary: 'Batalkan penerimaan (reversal)',
        tags: ['Receipt'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reverse(ReverseReceiptRequest $request, Receipt $receipt): JsonResponse
    {
        return $this->idempotency->resolve($request, function () use ($request, $receipt): JsonResponse {
            return $this->resource(new ReceiptResource(
                $this->reversal->reverse($receipt, $request->user(), $request->validated('reason'))
            ));
        });
    }
}
