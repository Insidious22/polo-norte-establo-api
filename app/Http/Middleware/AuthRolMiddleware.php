<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de seguridad por token (API Key / Bearer en el header Authorization).
 * - Sin token o token desconocido -> 401 Unauthorized
 * - Token válido pero con otro rol (ej. AYUDANTE en lugar de JEFE_ESTABLO) -> 403 Forbidden
 */
class AuthRolMiddleware
{
    public function handle(Request $request, Closure $next, string $rolRequerido): Response
    {
        $header = (string) $request->header('Authorization', '');
        $token = trim(preg_replace('/^Bearer\s+/i', '', $header));

        if ($token === '') {
            throw new ApiException(
                401,
                'NO_AUTENTICADO',
                'Se requiere un token de autenticación en el header Authorization'
            );
        }

        $tokensPorRol = config('seguridad.tokens', []);
        $rolDetectado = null;

        foreach ($tokensPorRol as $rol => $tokenConfigurado) {
            if (is_string($tokenConfigurado) && $tokenConfigurado !== '' && hash_equals($tokenConfigurado, $token)) {
                $rolDetectado = $rol;
                break;
            }
        }

        if ($rolDetectado === null) {
            throw new ApiException(
                401,
                'TOKEN_INVALIDO',
                'El token proporcionado no es válido'
            );
        }

        if ($rolDetectado !== $rolRequerido) {
            throw new ApiException(
                403,
                'ROL_INSUFICIENTE',
                "No tienes permisos para esta operación. Se requiere el rol {$rolRequerido} (rol actual: {$rolDetectado})"
            );
        }

        $request->attributes->set('rol_autenticado', $rolDetectado);

        return $next($request);
    }
}
