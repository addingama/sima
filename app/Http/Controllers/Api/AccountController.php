<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ListAccountRequest;
use App\Http\Requests\Master\StoreAccountRequest;
use App\Http\Requests\Master\UpdateAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Services\Master\AccountService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $service) {}

    #[OA\Get(
        path: '/accounts',
        summary: 'Daftar akun kas/bank',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListAccountRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Account::class);

        return $this->collection(AccountResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/accounts',
        summary: 'Buat akun kas/bank',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = $this->service->create($request->validated(), $request->user());

        return $this->created(new AccountResource($account));
    }

    #[OA\Get(
        path: '/accounts/{account}',
        summary: 'Detail akun kas/bank',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);

        return $this->resource(new AccountResource($this->service->findForShow($account)));
    }

    #[OA\Put(
        path: '/accounts/{account}',
        summary: 'Ubah akun kas/bank',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        return $this->resource(new AccountResource($this->service->update($account, $request->validated())));
    }

    #[OA\Delete(
        path: '/accounts/{account}',
        summary: 'Nonaktifkan akun kas/bank',
        tags: ['Account'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);

        $this->service->delete($account);

        return $this->message('Akun kas/bank dinonaktifkan (soft delete).');
    }
}
