<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

trait RespondsWithJson
{
    protected function ok(mixed $data = null, ?string $message = null, ?array $meta = null): JsonResponse
    {
        return ApiResponse::success($data, $message, $meta);
    }

    protected function resource(JsonResource $resource, ?string $message = null, int $status = 200): JsonResponse
    {
        return ApiResponse::resource($resource, $message, $status);
    }

    protected function created(JsonResource|array $data, ?string $message = null): JsonResponse
    {
        $payload = $data instanceof JsonResource ? $data->resolve(request()) : $data;

        return ApiResponse::created($payload, $message);
    }

    protected function collection(AnonymousResourceCollection $collection, ?string $message = null): JsonResponse
    {
        return ApiResponse::collection($collection, $message);
    }

    protected function message(?string $message = null, int $status = 200): JsonResponse
    {
        return ApiResponse::success(null, $message, null, $status);
    }
}
