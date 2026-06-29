<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreDonorRequest;
use App\Http\Requests\Master\UpdateDonorRequest;
use App\Http\Resources\DonorResource;
use App\Models\Donor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DonorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Donor::class);

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

        return DonorResource::collection($donors)->response();
    }

    public function store(StoreDonorRequest $request): JsonResponse
    {
        $donor = Donor::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return (new DonorResource($donor))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Donor $donor): JsonResponse
    {
        $this->authorize('view', $donor);

        return (new DonorResource($donor))->response();
    }

    public function update(UpdateDonorRequest $request, Donor $donor): JsonResponse
    {
        $donor->update($request->validated());

        return (new DonorResource($donor))->response();
    }

    public function destroy(Donor $donor): JsonResponse
    {
        $this->authorize('delete', $donor);

        $donor->delete();

        return response()->json(['message' => 'Donatur dinonaktifkan (soft delete).']);
    }
}
