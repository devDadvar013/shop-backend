<?php

use App\Exceptions\ApiExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // statefulApi() disabled — this is a stateless token API
        // (Sanctum personal access tokens via Authorization: Bearer header).
        // Re-enable only if migrating to Sanctum SPA session-based auth.
        // $middleware->statefulApi();
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return (new ApiExceptionHandler(app()))->render($request, $e);
            }
        });
    })->create();
