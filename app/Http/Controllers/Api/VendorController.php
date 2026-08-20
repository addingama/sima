<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ListVendorRequest;
use App\Http\Requests\Master\StoreVendorRequest;
use App\Http\Requests\Master\UpdateVendorRequest;
use App\Http\Resources\VendorResource;
use App\Models\Vendor;
use App\Services\Master\VendorService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class VendorController extends Controller
{
    public function __construct(private readonly VendorService $service) {}

    #[OA\Get(
        path: '/vendors',
        summary: 'Daftar vendor',
        tags: ['Vendor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListVendorRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Vendor::class);

        return $this->collection(VendorResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/vendors',
        summary: 'Buat vendor',
        tags: ['Vendor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreVendorRequest $request): JsonResponse
    {
        $vendor = $this->service->create($request->validated(), $request->user());

        return $this->created(new VendorResource($vendor));
    }

    #[OA\Get(
        path: '/vendors/{vendor}',
        summary: 'Detail vendor',
        tags: ['Vendor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Vendor $vendor): JsonResponse
    {
        $this->authorize('view', $vendor);

        return $this->resource(new VendorResource($vendor));
    }

    #[OA\Put(
        path: '/vendors/{vendor}',
        summary: 'Ubah vendor',
        tags: ['Vendor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateVendorRequest $request, Vendor $vendor): JsonResponse
    {
        return $this->resource(new VendorResource($this->service->update($vendor, $request->validated())));
    }

    #[OA\Delete(
        path: '/vendors/{vendor}',
        summary: 'Nonaktifkan vendor',
        tags: ['Vendor'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Vendor $vendor): JsonResponse
    {
        $this->authorize('delete', $vendor);

        $this->service->delete($vendor);

        return $this->message('Vendor dinonaktifkan (soft delete).');
    }
}
