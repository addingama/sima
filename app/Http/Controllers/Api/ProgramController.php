<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $programs = Program::query()
            ->with('fund:id,code,name')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%')
                ->orWhere('code', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('fund_id'), fn ($q) => $q->where('fund_id', $request->integer('fund_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json($programs);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fund_id' => ['nullable', 'exists:funds,id'],
            'code' => ['required', 'string', 'max:50', 'unique:programs,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:planned,active,closed'],
            'is_active' => ['boolean'],
        ]);
        $data['created_by'] = $request->user()->id;

        $program = Program::create($data);

        return response()->json($program->load('fund:id,code,name'), 201);
    }

    public function show(Program $program): JsonResponse
    {
        return response()->json($program->load('fund:id,code,name'));
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $data = $request->validate([
            'fund_id' => ['nullable', 'exists:funds,id'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:programs,code,'.$program->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'in:planned,active,closed'],
            'is_active' => ['boolean'],
        ]);

        $program->update($data);

        return response()->json($program->load('fund:id,code,name'));
    }

    public function destroy(Program $program): JsonResponse
    {
        $program->delete();

        return response()->json(['message' => 'Program dinonaktifkan (soft delete).']);
    }
}
