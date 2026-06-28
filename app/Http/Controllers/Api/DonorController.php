<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $donors = Donor::query()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $term = '%'.$request->string('q').'%';
                $w->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('email', 'like', $term);
            }))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return response()->json($donors);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:donors,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:individu,lembaga'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
        $data['created_by'] = $request->user()->id;

        $donor = Donor::create($data);

        return response()->json($donor, 201);
    }

    public function show(Donor $donor): JsonResponse
    {
        return response()->json($donor);
    }

    public function update(Request $request, Donor $donor): JsonResponse
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:donors,code,'.$donor->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'in:individu,lembaga'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'identity_number' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $donor->update($data);

        return response()->json($donor);
    }

    public function destroy(Donor $donor): JsonResponse
    {
        $donor->delete();

        return response()->json(['message' => 'Donatur dinonaktifkan (soft delete).']);
    }
}
