<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SIMA API',
    description: 'Sistem Informasi Manajemen Amanah — REST API',
)]
#[OA\Server(url: '/api', description: 'API base path')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum Token',
    description: 'Token dari POST /api/login',
)]
#[OA\Schema(
    schema: 'ApiEnvelope',
    properties: [
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'message', type: 'string', nullable: true),
        new OA\Property(property: 'data', nullable: true),
        new OA\Property(property: 'meta', nullable: true),
        new OA\Property(property: 'errors', nullable: true),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'offset'),
        new OA\Property(property: 'current_page', type: 'integer'),
        new OA\Property(property: 'per_page', type: 'integer'),
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'last_page', type: 'integer'),
    ],
    type: 'object',
)]
class OpenApiInfo {}
