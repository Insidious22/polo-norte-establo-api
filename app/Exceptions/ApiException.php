<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción estándar de la API para responder siempre con el formato JSON único.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $codigo,
        public readonly string $mensaje,
        public readonly array $detalles = []
    ) {
        parent::__construct($mensaje, $statusCode);
    }
}
