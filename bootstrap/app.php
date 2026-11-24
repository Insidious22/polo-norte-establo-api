<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\AuthRolMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => AuthRolMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $formatoError = function (int $status, string $codigo, string $mensaje, Request $request, array $detalles = []) {
            return response()->json([
                'codigo'    => $codigo,
                'mensaje'   => $mensaje,
                'detalles'  => $detalles,
                'timestamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                'ruta'      => $request->getPathInfo(),
            ], $status);
        };

        $exceptions->render(function (ApiException $e, Request $request) use ($formatoError) {
            return $formatoError($e->statusCode, $e->codigo, $e->mensaje, $request, $e->detalles);
        });

        // Errores de validación de formato HTTP responden 400 (422 se reserva para reglas de negocio)
        $exceptions->render(function (ValidationException $e, Request $request) use ($formatoError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $detalles = [];
                foreach ($e->errors() as $campo => $mensajes) {
                    foreach ($mensajes as $msg) {
                        $detalles[] = "{$campo}: {$msg}";
                    }
                }

                return $formatoError(
                    400,
                    'ENTRADA_INVALIDA',
                    'Los datos enviados no cumplen con el formato requerido',
                    $request,
                    $detalles
                );
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($formatoError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $formatoError(
                    404,
                    'RECURSO_NO_ENCONTRADO',
                    'El recurso o ruta solicitada no existe',
                    $request
                );
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($formatoError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $formatoError(
                    405,
                    'METODO_NO_PERMITIDO',
                    'El método HTTP utilizado no está permitido en esta ruta',
                    $request
                );
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($formatoError) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $formatoError(
                    500,
                    'ERROR_INTERNO',
                    'Ocurrió un error interno inesperado en el servidor',
                    $request
                );
            }
        });
    })->create();
