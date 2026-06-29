<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ListDonorRequest;
use App\Http\Requests\Master\StoreDonorRequest;
use App\Http\Requests\Master\UpdateDonorRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donor;
use App\Services\Master\DonorService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class DonorController extends Controller
{
    public function __construct(private readonly DonorService $service) {}

    #[OA\Get(
        path: '/donors',
        summary: 'Daftar donatur',
        tags: ['Donor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListDonorRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Donor::class);

        return $this->collection(DonorResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/donors',
        summary: 'Buat donatur',
        tags: ['Donor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreDonorRequest $request): JsonResponse
    {
        $donor = $this->service->create($request->validated(), $request->user());

        return $this->created(new DonorResource($donor));
    }

    #[OA\Get(
        path: '/donors/{donor}',
        summary: 'Detail donatur',
        tags: ['Donor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Donor $donor): JsonResponse
    {
        $this->authorize('view', $donor);

        return $this->resource(new DonorResource($donor));
    }

    #[OA\Put(
        path: '/donors/{donor}',
        summary: 'Ubah donatur',
        tags: ['Donor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateDonorRequest $request, Donor $donor): JsonResponse
    {
        return $this->resource(new DonorResource($this->service->update($donor, $request->validated())));
    }

    #[OA\Delete(
        path: '/donors/{donor}',
        summary: 'Nonaktifkan donatur',
        tags: ['Donor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Donor $donor): JsonResponse
    {
        $this->authorize('delete', $donor);

        $this->service->delete($donor);

        return $this->message('Donatur dinonaktifkan (soft delete).');
    }
}
