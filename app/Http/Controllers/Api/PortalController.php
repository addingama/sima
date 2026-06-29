<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ListPortalDonationRequest;
use App\Http\Resources\Portal\PortalDonorResource;
use App\Http\Resources\Portal\PortalReceiptResource;
use App\Services\Portal\PortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/** Portal Donatur — donatur hanya dapat melihat data miliknya sendiri. */
class PortalController extends Controller
{
    public function __construct(private readonly PortalService $service) {}

    #[OA\Get(
        path: '/portal/profile',
        summary: 'Profil donatur portal',
        tags: ['Portal'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function profile(Request $request): JsonResponse
    {
        return $this->resource(new PortalDonorResource($this->service->profile($request->user())));
    }

    #[OA\Get(
        path: '/portal/donations',
        summary: 'Riwayat donasi donatur',
        tags: ['Portal'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function donations(ListPortalDonationRequest $request): JsonResponse
    {
        return $this->collection(PortalReceiptResource::collection(
            $this->service->donations($request->user(), $request->listQuery())
        ));
    }

    #[OA\Get(
        path: '/portal/summary',
        summary: 'Ringkasan donasi donatur',
        tags: ['Portal'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function summary(Request $request): JsonResponse
    {
        return $this->ok($this->service->summary($request->user()));
    }
}
