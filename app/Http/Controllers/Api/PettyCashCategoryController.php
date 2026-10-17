<?php

namespace App\Http\Controllers\Api;

use App\Domains\PettyCash\Services\PettyCashCategoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PettyCash\ListPettyCashCategoryRequest;
use App\Http\Requests\PettyCash\StorePettyCashCategoryRequest;
use App\Http\Requests\PettyCash\UpdatePettyCashCategoryRequest;
use App\Http\Resources\PettyCashCategoryResource;
use App\Models\PettyCashCategory;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PettyCashCategoryController extends Controller
{
    public function __construct(private readonly PettyCashCategoryService $service) {}

    #[OA\Get(path: '/petty-cash-categories', summary: 'Daftar kategori belanja kas kecil', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function index(ListPettyCashCategoryRequest $request): JsonResponse
    {
        return $this->collection(PettyCashCategoryResource::collection(
            $this->service->paginate($request->listQuery(), $request->activeOnly())
        ));
    }

    #[OA\Post(path: '/petty-cash-categories', summary: 'Buat kategori belanja', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function store(StorePettyCashCategoryRequest $request): JsonResponse
    {
        return $this->created(new PettyCashCategoryResource($this->service->create($request->validated())));
    }

    #[OA\Get(path: '/petty-cash-categories/{pettyCashCategory}', summary: 'Detail kategori', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function show(PettyCashCategory $pettyCashCategory): JsonResponse
    {
        $this->authorize('view', $pettyCashCategory);

        return $this->resource(new PettyCashCategoryResource($pettyCashCategory));
    }

    #[OA\Put(path: '/petty-cash-categories/{pettyCashCategory}', summary: 'Ubah kategori', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function update(UpdatePettyCashCategoryRequest $request, PettyCashCategory $pettyCashCategory): JsonResponse
    {
        return $this->resource(new PettyCashCategoryResource(
            $this->service->update($pettyCashCategory, $request->validated())
        ));
    }

    #[OA\Delete(path: '/petty-cash-categories/{pettyCashCategory}', summary: 'Nonaktifkan kategori', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function destroy(PettyCashCategory $pettyCashCategory): JsonResponse
    {
        $this->authorize('delete', $pettyCashCategory);

        return $this->resource(new PettyCashCategoryResource(
            $this->service->deactivate($pettyCashCategory)
        ));
    }
}
