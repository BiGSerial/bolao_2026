<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureLegalAcceptance;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'user.active' => EnsureUserIsActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'legal.accepted' => EnsureLegalAcceptance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/v1/*')) {
                return null;
            }

            return ApiResponse::error(
                request: $request,
                code: 'VALIDATION_ERROR',
                message: 'Dados inválidos.',
                status: 422,
                details: ['fields' => $exception->errors()],
            );
        });
    })->create();
