<?php

use App\Exceptions\DomainException;
use App\Exceptions\InsufficientBalanceException;
use App\Http\Middleware\AssignRequestId;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (InsufficientBalanceException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 'insufficient_balance', status: Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error($e->getMessage(), 'domain_rule_violation', status: Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error(
                    'Validasi gagal.',
                    'validation_error',
                    $e->errors(),
                    status: $e->status,
                );
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Anda tidak memiliki izin untuk aksi ini.', 'forbidden', status: Response::HTTP_FORBIDDEN);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Tidak terautentikasi.', 'unauthenticated', status: Response::HTTP_UNAUTHORIZED);
            }
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Data tidak ditemukan.', 'not_found', status: Response::HTTP_NOT_FOUND);
            }
        });
    })->create();
