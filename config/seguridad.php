<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tokens de API por Rol (leídos desde .env, nunca hardcodeados)
    |--------------------------------------------------------------------------
    | Sin token o token inválido  -> 401 Unauthorized
    | Token con rol insuficiente  -> 403 Forbidden
    */
    'tokens' => [
        'JEFE_ESTABLO' => env('API_TOKEN_JEFE_ESTABLO', ''),
        'AYUDANTE'     => env('API_TOKEN_AYUDANTE', ''),
    ],
];
