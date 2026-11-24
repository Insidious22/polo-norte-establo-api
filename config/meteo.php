<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Servicio Externo SOAP: MeteoPoloNorteService
    |--------------------------------------------------------------------------
    */
    'url'             => env('SOAP_METEO_URL', 'http://127.0.0.1:4001/meteo'),
    'timeout_seconds' => (float) env('SOAP_TIMEOUT_SECONDS', 3),
    'max_attempts'    => (int) env('SOAP_MAX_ATTEMPTS', 2),
    'backoff_ms'      => (int) env('SOAP_BACKOFF_MS', 300),
];
