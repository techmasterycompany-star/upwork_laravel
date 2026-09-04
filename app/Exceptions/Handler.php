<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function register()
    {
        $this->renderable(function (Throwable $e, $request) {

            // Apply this only to API requests
            if (! $request->is('api/*')) {
                return null;
            }

            // Validation errors
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $e->errors(),
                ], 422);
            }

            // Authentication
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            // Authorization
            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to perform this action.',
                ], 403);
            }

            // Model not found
            if ($e instanceof ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested resource was not found.',
                ], 404);
            }

            // Route not found
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint not found.',
                ], 404);
            }

            // Wrong HTTP method
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method not allowed for this endpoint.',
                ], 405);
            }

            // Too many requests
            if ($e instanceof ThrottleRequestsException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ], 429);
            }

            // Any unexpected server error
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again later.',
            ], 500);
        });
    }
}