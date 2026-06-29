<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProgramRequest;
use App\Http\Requests\Master\UpdateProgramRequest;
use App\Http\Resources\ProgramResource;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Program::class);

        $programs = Program::query()
            ->with('fund:id,code,name')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->integer('fund_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return ProgramResource::collection($programs)->response();
    }

    public function store(StoreProgramRequest $request): JsonResponse
    {
        $program = Program::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return (new ProgramResource($program->load('fund:id,code,name')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Program $program): JsonResponse
    {
        $this->authorize('view', $program);

        return (new ProgramResource($program->load('fund:id,code,name')))->response();
    }

    public function update(UpdateProgramRequest $request, Program $program): JsonResponse
    {
        $program->update($request->validated());

        return (new ProgramResource($program->load('fund:id,code,name')))->response();
    }

    public function destroy(Program $program): JsonResponse
    {
        $this->authorize('delete', $program);

        $program->delete();

        return response()->json(['message' => 'Program dinonaktifkan (soft delete).']);
    }
}
