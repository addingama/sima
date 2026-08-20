<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Requests\User\ResetUserPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\SyncUserRolesRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class UserController extends Controller
{
    public function __construct(private readonly UserService $service) {}

    #[OA\Get(
        path: '/users',
        summary: 'Daftar pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function index(ListUserRequest $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        return $this->collection(UserResource::collection($this->service->paginate($request->listQuery())));
    }

    #[OA\Post(
        path: '/users',
        summary: 'Buat pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->service->create($request->validated(), $request->user());

        return $this->created(new UserResource($user));
    }

    #[OA\Get(
        path: '/users/{user}',
        summary: 'Detail pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->resource(new UserResource($user->load('roles')));
    }

    #[OA\Put(
        path: '/users/{user}',
        summary: 'Ubah pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return $this->resource(new UserResource($this->service->update($user, $request->validated(), $request->user())));
    }

    #[OA\Delete(
        path: '/users/{user}',
        summary: 'Nonaktifkan pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->service->deactivate($user, request()->user());

        return $this->message('Pengguna dinonaktifkan.');
    }

    #[OA\Put(
        path: '/users/{user}/roles',
        summary: 'Sync role pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $updated = $this->service->syncRole($user, $request->string('role')->toString(), $request->user());

        return $this->resource(new UserResource($updated));
    }

    #[OA\Post(
        path: '/users/{user}/reset-password',
        summary: 'Reset password pengguna',
        tags: ['User'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $updated = $this->service->resetPassword($user, $request->string('password')->toString(), $request->user());

        return $this->resource(new UserResource($updated));
    }
}
