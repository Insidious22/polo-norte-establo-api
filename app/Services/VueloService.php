<?php

namespace App\Services;

use App\Models\Reno;
use App\Repositories\MantenimientoRepository;
use App\Repositories\RenoRepository;

/**
 * Servicio del endpoint extra del Escenario B: GET /api/vuelo/resumen.
 */
class VueloService
{
    public function __construct(
        private readonly RenoRepository $renoRepository,
        private readonly MantenimientoRepository $mantenimientoRepository
    ) {}

    public function obtenerResumen(): array
    {
        $renosAsignados = $this->renoRepository->obtenerAsignadosAlVuelo();
        $totalAsignados = $renosAsignados->count();

        $energiaPromedio = $totalAsignados > 0
            ? round((float) $renosAsignados->avg('nivel_energia'), 2)
            : 0.0;

        $pistaLista = $this->mantenimientoRepository->existePistaCompletada();
        $vueloListo = ($totalAsignados === Reno::MAX_RENOS_VUELO) && $pistaLista;

        return [
            'totalRenosAsignados'             => $totalAsignados,
            'renosRequeridos'                 => Reno::MAX_RENOS_VUELO,
            'energiaPromedio'                 => $energiaPromedio,
            'pistaConMantenimientoCompletado' => $pistaLista,
            'vueloListo'                      => $vueloListo,
            'renosAsignados'                  => $renosAsignados->values()->toArray(),
        ];
    }
}
