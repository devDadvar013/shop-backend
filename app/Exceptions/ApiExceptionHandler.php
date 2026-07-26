<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ApiExceptionHandler extends ExceptionHandler
{
    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        if ($this->shouldReturnJson($request)) {
            return $this->renderJson($e);
        }
        return parent::render($request, $e);
    }

    protected function shouldReturnJson($request, Throwable $e = null): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    protected function renderJson(Throwable $e): JsonResponse
    {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors'  => $e->errors(),
            ], 422);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($e instanceof HttpException) {
            return response()->json([
                'message' => $e->getMessage() ?: 'HTTP error.',
            ], $e->getStatusCode());
        }

        $status = 500;
        $payload = ['message' => 'Server error.'];

        if (config('app.debug')) {
            $payload['exception'] = get_class($e);
            $payload['error']     = $e->getMessage();
            $payload['trace']     = collect($e->getTrace())->take(8)->all();
        }

        return response()->json($payload, $status);
    }

    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse
    {
        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
