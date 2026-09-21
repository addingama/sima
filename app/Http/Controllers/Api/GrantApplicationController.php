<?php

namespace App\Http\Controllers\Api;

use App\Domains\Grant\Services\GrantApplicationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\GrantApplication\ApproveGrantApplicationRequest;
use App\Http\Requests\GrantApplication\AssignGrantApplicationRequest;
use App\Http\Requests\GrantApplication\CompleteGrantApplicationRequest;
use App\Http\Requests\GrantApplication\CreateGrantDisbursementRequest;
use App\Http\Requests\GrantApplication\ListGrantApplicationRequest;
use App\Http\Requests\GrantApplication\RejectGrantApplicationRequest;
use App\Http\Requests\GrantApplication\ReturnGrantApplicationRequest;
use App\Http\Requests\GrantApplication\SendToVerificationRequest;
use App\Http\Requests\GrantApplication\StoreGrantApplicationRequest;
use App\Http\Requests\GrantApplication\SubmitForApprovalRequest;
use App\Http\Requests\GrantApplication\UpdateGrantApplicationRequest;
use App\Http\Resources\GrantApplicationResource;
use App\Models\GrantApplication;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class GrantApplicationController extends Controller
{
    public function __construct(private readonly GrantApplicationService $service) {}

    #[OA\Get(
        path: '/grant-applications',
        summary: 'Daftar pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListGrantApplicationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', GrantApplication::class);

        return $this->collection(GrantApplicationResource::collection(
            $this->service->paginate($request->listQuery(), $request->user())
        ));
    }

    #[OA\Post(
        path: '/grant-applications',
        summary: 'Buat pengajuan bantuan (rekomendasi)',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreGrantApplicationRequest $request): JsonResponse
    {
        $grant = $this->service->create($request->grantData(), $request->user());

        return $this->created(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Get(
        path: '/grant-applications/{grant_application}',
        summary: 'Detail pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(GrantApplication $grantApplication): JsonResponse
    {
        $this->authorize('view', $grantApplication);

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grantApplication)));
    }

    #[OA\Put(
        path: '/grant-applications/{grant_application}',
        summary: 'Ubah pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateGrantApplicationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->update($grantApplication, $request->grantData(), $request->user());

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/assign',
        summary: 'Tugaskan verifikator',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function assign(AssignGrantApplicationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->assign(
            $grantApplication,
            (int) $request->validated('assigned_verifier_id'),
            $request->user(),
        );

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/send-to-verification',
        summary: 'Kirim ke kolom verifikasi',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function sendToVerification(SendToVerificationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->sendToVerification($grantApplication, $request->user());

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/submit-for-approval',
        summary: 'Ajukan ke approval setelah verifikasi lengkap',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function submitForApproval(SubmitForApprovalRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->submitForApproval($grantApplication, $request->user());

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/approve',
        summary: 'Setujui pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function approve(ApproveGrantApplicationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->approve(
            $grantApplication,
            $request->user(),
            $request->validated('approved_amount'),
            $request->validated('notes'),
        );

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/reject',
        summary: 'Tolak pengajuan bantuan',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function reject(RejectGrantApplicationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->reject($grantApplication, $request->user(), $request->validated('reason'));

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/return',
        summary: 'Kembalikan ke verifikasi',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function returnToVerification(ReturnGrantApplicationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->returnToVerification(
            $grantApplication,
            $request->user(),
            $request->validated('reason'),
        );

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/disbursements',
        summary: 'Buat pengeluaran tertaut 1:1 dari pengajuan approved',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function createDisbursement(CreateGrantDisbursementRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->createLinkedDisbursement(
            $grantApplication,
            $request->expenseData(),
            $request->sources(),
            $request->user(),
        );

        return $this->created(new GrantApplicationResource($this->service->findForShow($grant)));
    }

    #[OA\Post(
        path: '/grant-applications/{grant_application}/complete',
        summary: 'Tandai serah terima selesai',
        tags: ['GrantApplication'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function complete(CompleteGrantApplicationRequest $request, GrantApplication $grantApplication): JsonResponse
    {
        $grant = $this->service->complete(
            $grantApplication,
            $request->user(),
            $request->validated('handed_over_on'),
        );

        return $this->resource(new GrantApplicationResource($this->service->findForShow($grant)));
    }
}
