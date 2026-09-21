<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Resources\RolePermissionIndexResource;
use App\Services\User\RolePermissionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function __construct(private readonly RolePermissionService $service) {}

    #[OA\Get(
        path: '/roles',
        summary: 'Daftar role dan permission SIMA',
        tags: ['Role'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function index(): JsonResponse
    {
        return $this->resource(new RolePermissionIndexResource($this->service->index()));
    }

    #[OA\Put(
        path: '/roles/{role}/permissions',
        summary: 'Sinkronisasi permission role',
        tags: ['Role'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK')]
    )]
    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $this->service->syncPermissions($role, $request->validated('permissions'), $request->user());

        return $this->resource(
            new RolePermissionIndexResource($this->service->index()),
            'Permission role berhasil diperbarui.',
        );
    }
}
