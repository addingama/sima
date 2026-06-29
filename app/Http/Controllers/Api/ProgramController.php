<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ListProgramRequest;
use App\Http\Requests\Master\StoreProgramRequest;
use App\Http\Requests\Master\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use App\Services\Master\ProgramService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProgramController extends Controller
{
    public function __construct(private readonly ProgramService $service) {}

    #[OA\Get(
        path: '/programs',
        summary: 'Daftar program',
        tags: ['Program'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListProgramRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Program::class);

        return $this->collection(ProgramResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/programs',
        summary: 'Buat program',
        tags: ['Program'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreProgramRequest $request): JsonResponse
    {
        $program = $this->service->create($request->validated(), $request->user());

        return $this->created(new ProgramResource($this->service->findForShow($program)));
    }

    #[OA\Get(
        path: '/programs/{program}',
        summary: 'Detail program',
        tags: ['Program'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Program $program): JsonResponse
    {
        $this->authorize('view', $program);

        return $this->resource(new ProgramResource($this->service->findForShow($program)));
    }

    #[OA\Put(
        path: '/programs/{program}',
        summary: 'Ubah program',
        tags: ['Program'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        $program = $this->service->update($program, $request->validated());

        return $this->resource(new ProgramResource($this->service->findForShow($program)));
    }

    #[OA\Delete(
        path: '/programs/{program}',
        summary: 'Nonaktifkan program',
        tags: ['Program'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Program $program): JsonResponse
    {
        $this->authorize('delete', $program);

        $this->service->delete($program);

        return $this->message('Program dinonaktifkan (soft delete).');
    }
}
