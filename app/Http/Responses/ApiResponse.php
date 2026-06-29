<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

final class ApiResponse
{
    /** @param  array<string, mixed>|null  $meta */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        ?array $meta = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => null,
        ], $status);
    }

    public static function created(mixed $data = null, ?string $message = 'Berhasil dibuat.'): JsonResponse
    {
        return self::success($data, $message, null, 201);
    }

    public static function resource(JsonResource $resource, ?string $message = null, int $status = 200): JsonResponse
    {
        return self::success($resource->resolve(request()), $message, null, $status);
    }

    public static function collection(AnonymousResourceCollection $collection, ?string $message = null): JsonResponse
    {
        $underlying = $collection->resource;

        if ($underlying instanceof LengthAwarePaginator) {
            return self::paginated($collection, $message);
        }

        if ($underlying instanceof CursorPaginator) {
            return self::cursorPaginated($collection, $message);
        }

        return self::success($collection->resolve(request()), $message);
    }

    public static function paginated(AnonymousResourceCollection $collection, ?string $message = null): JsonResponse
    {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $collection->resource;

        return self::success(
            $collection->resolve(request()),
            $message,
            ['pagination' => self::lengthAwareMeta($paginator)],
        );
    }

    public static function cursorPaginated(AnonymousResourceCollection $collection, ?string $message = null): JsonResponse
    {
        /** @var CursorPaginator $paginator */
        $paginator = $collection->resource;

        return self::success(
            $collection->resolve(request()),
            $message,
            ['pagination' => self::cursorMeta($paginator)],
        );
    }

    /**
     * @param  array<string, mixed>|null  $fields
     * @param  array<string, mixed>|null  $meta
     */
    public static function error(
        string $message,
        ?string $code = null,
        ?array $fields = null,
        ?array $meta = null,
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => $meta,
            'errors' => array_filter([
                'code' => $code,
                'fields' => $fields,
            ], fn ($v) => $v !== null),
        ], $status);
    }

    /** @return array<string, mixed> */
    public static function lengthAwareMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'type' => 'offset',
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /** @return array<string, mixed> */
    public static function cursorMeta(CursorPaginator $paginator): array
    {
        return [
            'type' => 'cursor',
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ];
    }
}
