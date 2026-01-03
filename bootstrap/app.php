<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Models\ActivityLog;
use App\Services\AdminNotificationService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException as LaravelValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php', // Enable API routes
    commands: __DIR__ . '/../routes/console.php',
    health: '/up',
  )
  ->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
      'admin' => AdminMiddleware::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions): void {
    // Handle all exceptions
    $exceptions->render(function (Throwable $e, $request) {
      // Only handle API requests
      if (!$request->is('api/*')) {
        return null; // Let default handler handle non-API routes
      }

      $statusCode = 500;
      $message = 'An unexpected error occurred';
      $errorCode = 'INTERNAL_SERVER_ERROR';
      $details = null;

      // Determine status code and message based on exception type
      if ($e instanceof HttpException) {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage() ?: 'HTTP Error';
        $errorCode = 'HTTP_' . $statusCode;
      } elseif ($e instanceof ModelNotFoundException) {
        $statusCode = 404;
        $message = 'Resource not found';
        $errorCode = 'MODEL_NOT_FOUND';
      } elseif ($e instanceof NotFoundHttpException) {
        $statusCode = 404;
        $message = 'Endpoint not found';
        $errorCode = 'ROUTE_NOT_FOUND';
      } elseif ($e instanceof MethodNotAllowedHttpException) {
        $statusCode = 405;
        $message = 'Method not allowed';
        $errorCode = 'METHOD_NOT_ALLOWED';
      } elseif ($e instanceof AuthenticationException) {
        $statusCode = 401;
        $message = 'Unauthenticated';
        $errorCode = 'UNAUTHENTICATED';
      } elseif ($e instanceof LaravelValidationException) {
        $statusCode = 422;
        $message = 'Validation failed';
        $errorCode = 'VALIDATION_ERROR';
        $details = ['errors' => $e->errors()];
      } elseif (method_exists($e, 'getStatusCode')) {
        $statusCode = $e->getStatusCode();
        $message = $e->getMessage();
      } else {
        $message = config('app.debug') ? $e->getMessage() : 'An unexpected error occurred';
      }

      // Log critical errors to activity logs and notify admins
      if ($statusCode >= 500) {
        try {
          $activityLog = ActivityLog::log(
            'system_error',
            "System error: {$message}",
            null,
            null,
            [
              'exception' => get_class($e),
              'message' => $e->getMessage(),
              'file' => $e->getFile(),
              'line' => $e->getLine(),
              'url' => $request->fullUrl(),
              'method' => $request->method(),
            ],
            'critical'
          );

          // Notify admins of critical error
          AdminNotificationService::notifySystemError(
            $message,
            [
              'exception' => get_class($e),
              'url' => $request->fullUrl(),
              'method' => $request->method(),
            ],
            $activityLog->id
          );
        } catch (\Exception $logError) {
          // Silently fail if activity logging or notification fails
        }
      }

      // Build response
      $response = [
        'success' => false,
        'message' => $message,
        'error_code' => $errorCode,
      ];

      if ($details !== null) {
        $response['details'] = $details;
      }

      // Add debug information in debug mode
      if (config('app.debug')) {
        $response['debug'] = [
          'exception' => get_class($e),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'trace' => collect($e->getTrace())->take(5)->toArray(),
        ];
      }

      return response()->json($response, $statusCode);
    });
  })->create();
