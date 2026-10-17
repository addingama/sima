<?php

namespace App\Http\Controllers\Api;

use App\Domains\PettyCash\Services\PettyCashFloatService;
use App\Http\Controllers\Controller;
use App\Http\Requests\PettyCash\ListPettyCashFloatRequest;
use App\Http\Requests\PettyCash\StorePettyCashFloatRequest;
use App\Http\Requests\PettyCash\StorePettyCashLineRequest;
use App\Http\Requests\PettyCash\UpdatePettyCashFloatRequest;
use App\Http\Requests\PettyCash\UpdatePettyCashLineRequest;
use App\Http\Resources\PettyCashFloatResource;
use App\Http\Resources\PettyCashLineResource;
use App\Models\PettyCashFloat;
use App\Models\PettyCashLine;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PettyCashFloatController extends Controller
{
    public function __construct(private readonly PettyCashFloatService $service) {}

    #[OA\Get(path: '/petty-cash-floats', summary: 'Daftar float kas kecil', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function index(ListPettyCashFloatRequest $request): JsonResponse
    {
        return $this->collection(PettyCashFloatResource::collection(
            $this->service->paginate($request->listQuery(), $request->user())
        ));
    }

    #[OA\Get(path: '/petty-cash-floats/custodians', summary: 'Daftar user yang bisa jadi PJ', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function custodians(): JsonResponse
    {
        $this->authorize('create', PettyCashFloat::class);

        $users = User::query()
            ->permission('petty_cash.update')
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        return $this->ok($users);
    }

    #[OA\Post(path: '/petty-cash-floats', summary: 'Buat float kas kecil', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function store(StorePettyCashFloatRequest $request): JsonResponse
    {
        return $this->created(new PettyCashFloatResource(
            $this->service->create($request->validated(), $request->user())
        ));
    }

    #[OA\Get(path: '/petty-cash-floats/{pettyCashFloat}', summary: 'Detail float', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function show(PettyCashFloat $pettyCashFloat): JsonResponse
    {
        $this->authorize('view', $pettyCashFloat);

        return $this->resource(new PettyCashFloatResource(
            $this->service->findForShow($pettyCashFloat)
        ));
    }

    #[OA\Put(path: '/petty-cash-floats/{pettyCashFloat}', summary: 'Ubah float', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function update(UpdatePettyCashFloatRequest $request, PettyCashFloat $pettyCashFloat): JsonResponse
    {
        return $this->resource(new PettyCashFloatResource(
            $this->service->update($pettyCashFloat, $request->validated(), $request->user())
        ));
    }

    #[OA\Post(path: '/petty-cash-floats/{pettyCashFloat}/cancel', summary: 'Batalkan float draft', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function cancel(PettyCashFloat $pettyCashFloat): JsonResponse
    {
        $this->authorize('cancel', $pettyCashFloat);

        return $this->resource(new PettyCashFloatResource(
            $this->service->cancel($pettyCashFloat)
        ));
    }

    #[OA\Post(path: '/petty-cash-floats/{pettyCashFloat}/lines', summary: 'Tambah baris belanja', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function storeLine(StorePettyCashLineRequest $request, PettyCashFloat $pettyCashFloat): JsonResponse
    {
        return $this->created(new PettyCashLineResource(
            $this->service->addLine($pettyCashFloat, $request->validated(), $request->user())
        ));
    }

    #[OA\Put(path: '/petty-cash-floats/{pettyCashFloat}/lines/{pettyCashLine}', summary: 'Ubah baris belanja', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function updateLine(
        UpdatePettyCashLineRequest $request,
        PettyCashFloat $pettyCashFloat,
        PettyCashLine $pettyCashLine,
    ): JsonResponse {
        return $this->resource(new PettyCashLineResource(
            $this->service->updateLine($pettyCashFloat, $pettyCashLine, $request->validated())
        ));
    }

    #[OA\Delete(path: '/petty-cash-floats/{pettyCashFloat}/lines/{pettyCashLine}', summary: 'Hapus baris belanja', tags: ['PettyCash'], security: [['sanctum' => []]])]
    public function destroyLine(PettyCashFloat $pettyCashFloat, PettyCashLine $pettyCashLine): JsonResponse
    {
        $this->authorize('manageLines', $pettyCashFloat);
        $this->service->deleteLine($pettyCashFloat, $pettyCashLine);

        return $this->message('Baris belanja dihapus.');
    }
}
