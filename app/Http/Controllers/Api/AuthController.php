<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    #[OA\Post(
        path: '/login',
        summary: 'Login dan dapatkan token Sanctum',
        tags: ['Auth'],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
            $request->ip(),
        );

        return $this->ok($result);
    }

    #[OA\Get(
        path: '/me',
        summary: 'Profil pengguna saat ini',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function me(Request $request): JsonResponse
    {
        return $this->resource(new UserResource($request->user()));
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Logout (hapus token saat ini)',
        tags: ['Auth'],
        security: [['sanctum' => []]],
        responses: [new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ApiEnvelope'))]
    )]
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());

        return $this->message('Berhasil keluar.');
    }
}
