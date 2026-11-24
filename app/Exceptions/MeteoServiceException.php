<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Excepción de dominio lanzada exclusivamente por MeteoAdapter
 * cuando MeteoPoloNorteService responde con soap:Fault, supera el timeout o no está disponible.
 * Oculta por completo los detalles XML/SOAP al resto del sistema.
 */
class MeteoServiceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $motivo = 'SERVICIO_NO_DISPONIBLE'
    ) {
        parent::__construct($message);
    }
}
