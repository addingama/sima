<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ListFundRequest;
use App\Http\Requests\Master\StoreFundRequest;
use App\Http\Requests\Master\UpdateFundRequest;
use App\Http\Resources\FundResource;
use App\Models\Fund;
use App\Services\Master\FundService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class FundController extends Controller
{
    public function __construct(private readonly FundService $service) {}

    #[OA\Get(
        path: '/funds',
        summary: 'Daftar Dana Amanah',
        tags: ['Fund'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListFundRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Fund::class);

        return $this->collection(FundResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/funds',
        summary: 'Buat Dana Amanah',
        tags: ['Fund'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreFundRequest $request): JsonResponse
    {
        $fund = $this->service->create($request->validated(), $request->user());

        return $this->created(new FundResource($fund));
    }

    #[OA\Get(
        path: '/funds/{fund}',
        summary: 'Detail Dana Amanah',
        tags: ['Fund'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Fund $fund): JsonResponse
    {
        $this->authorize('view', $fund);

        return $this->resource(new FundResource($this->service->findForShow($fund)));
    }

    #[OA\Put(
        path: '/funds/{fund}',
        summary: 'Ubah Dana Amanah',
        tags: ['Fund'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateFundRequest $request, Fund $fund): JsonResponse
    {
        return $this->resource(new FundResource($this->service->update($fund, $request->validated())));
    }

    #[OA\Delete(
        path: '/funds/{fund}',
        summary: 'Nonaktifkan Dana Amanah',
        tags: ['Fund'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Fund $fund): JsonResponse
    {
        $this->authorize('delete', $fund);

        $this->service->delete($fund);

        return $this->message('Dana Amanah dinonaktifkan (soft delete).');
    }
}
