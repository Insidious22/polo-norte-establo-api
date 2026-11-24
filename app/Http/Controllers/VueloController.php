<?php

namespace App\Http\Controllers;

use App\Services\VueloService;
use Illuminate\Http\JsonResponse;

/**
 * Controlador delgado para el endpoint extra GET /api/vuelo/resumen.
 */
class VueloController extends Controller
{
    public function __construct(
        private readonly VueloService $vueloService
    ) {}

    public function resumen(): JsonResponse
    {
        return response()->json($this->vueloService->obtenerResumen(), 200);
    }
}
