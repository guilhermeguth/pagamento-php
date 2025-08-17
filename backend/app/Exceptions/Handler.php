<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response|JsonResponse
    {
        // Handle API requests with JSON responses
        if ($request->expectsJson()) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    /**
     * Handle API exceptions with consistent JSON responses
     */
    private function handleApiException(Request $request, Throwable $exception): JsonResponse
    {
        $statusCode = 500;
        $message = 'Erro interno do servidor';
        $errors = null;

        switch (true) {
            case $exception instanceof ValidationException:
                $statusCode = 422;
                $message = 'Dados de entrada inválidos';
                $errors = $exception->errors();
                break;

            case $exception instanceof AuthenticationException:
                $statusCode = 401;
                $message = 'Não autenticado';
                break;

            case $exception instanceof AuthorizationException:
                $statusCode = 403;
                $message = 'Acesso negado';
                break;

            case $exception instanceof ModelNotFoundException:
            case $exception instanceof NotFoundHttpException:
                $statusCode = 404;
                $message = 'Recurso não encontrado';
                break;

            case $exception instanceof MethodNotAllowedHttpException:
                $statusCode = 405;
                $message = 'Método não permitido';
                break;

            default:
                // Log the exception for debugging
                Log::error('API Exception', [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                    'request' => [
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]
                ]);

                // Don't expose internal errors in production
                if (!config('app.debug')) {
                    $message = 'Erro interno do servidor';
                } else {
                    $message = $exception->getMessage();
                }
                break;
        }

        $response = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        // Add debug information in development
        if (config('app.debug')) {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Convert an authentication exception into a response.
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response|JsonResponse|\Illuminate\Http\RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Token de autenticação inválido ou expirado'
            ], 401);
        }

        return redirect()->guest('/login');
    }
}
