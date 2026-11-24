<?php

namespace App\Services;

use App\Adapters\MeteoAdapter;
use App\Exceptions\ApiException;
use App\Exceptions\MeteoServiceException;
use App\Models\Mantenimiento;
use App\Repositories\MantenimientoRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Capa de servicios para Mantenimientos del terreno.
 * Aquí viven las reglas de negocio 3, 4 y el manejo del fallo distribuido del servicio SOAP.
 * Esta clase NO conoce XML; toda la comunicación SOAP pasa por MeteoAdapter.
 */
class MantenimientoService
{
    /**
     * Regla 3: Transiciones válidas de estado para mantenimiento.
     */
    private const TRANSICIONES_VALIDAS = [
        Mantenimiento::ESTADO_PROGRAMADO => [
            Mantenimiento::ESTADO_EN_CURSO,
            Mantenimiento::ESTADO_CANCELADO,
        ],
        Mantenimiento::ESTADO_EN_CURSO => [
            Mantenimiento::ESTADO_COMPLETADO,
        ],
        Mantenimiento::ESTADO_COMPLETADO      => [],
        Mantenimiento::ESTADO_CANCELADO       => [],
        Mantenimiento::ESTADO_PENDIENTE_CLIMA => [],
    ];

    public function __construct(
        private readonly MantenimientoRepository $mantenimientoRepository,
        private readonly MeteoAdapter $meteoAdapter
    ) {}

    public function listar(?string $zona = null): Collection
    {
        if ($zona !== null && $zona !== '' && !in_array($zona, Mantenimiento::ZONAS_VALIDAS, true)) {
            throw new ApiException(
                400,
                'FILTRO_ZONA_INVALIDO',
                'El filtro zona no es válido',
                ['Zonas permitidas: ' . implode(', ', Mantenimiento::ZONAS_VALIDAS)]
            );
        }

        return $this->mantenimientoRepository->listar($zona);
    }

    public function obtenerPorId(int $id): Mantenimiento
    {
        $mantenimiento = $this->mantenimientoRepository->buscarPorId($id);

        if (!$mantenimiento) {
            throw new ApiException(
                404,
                'MANTENIMIENTO_NO_ENCONTRADO',
                "No existe un mantenimiento con el id {$id}"
            );
        }

        return $mantenimiento;
    }

    /**
     * Programa un mantenimiento aplicando:
     * - Validación de fecha no pasada (400).
     * - Regla 4: En PISTA_DESPEGUE consulta el clima vía MeteoAdapter; si es VENTISCA -> 422.
     * - Fallo distribuido SOAP: si MeteoAdapter falla (Fault o timeout 3s tras reintentos),
     *   guarda en estado PENDIENTE_CLIMA y responde 202 Accepted (nunca 500).
     */
    public function programar(array $datos): array
    {
        $fecha = Carbon::createFromFormat('Y-m-d', $datos['fechaProgramada'])->startOfDay();
        $hoy = Carbon::today();

        if ($fecha->lt($hoy)) {
            throw new ApiException(
                400,
                'FECHA_PASADA',
                'La fechaProgramada no puede ser una fecha pasada',
                ["fechaProgramada ({$datos['fechaProgramada']}) debe ser igual o posterior a {$hoy->format('Y-m-d')}"]
            );
        }

        $estadoInicial = Mantenimiento::ESTADO_PROGRAMADO;
        $statusHttp = 201;
        $aviso = null;

        // Regla 4: Solo las tareas en la zona exterior PISTA_DESPEGUE consultan el servicio SOAP
        if ($datos['zona'] === Mantenimiento::ZONA_PISTA_DESPEGUE) {
            try {
                $clima = $this->meteoAdapter->obtenerPronostico(
                    $datos['zona'],
                    $datos['fechaProgramada']
                );

                if ($clima['pronostico'] === MeteoAdapter::PRONOSTICO_VENTISCA) {
                    throw new ApiException(
                        422,
                        'CLIMA_ADVERSO_VENTISCA',
                        'No se puede programar mantenimiento en PISTA_DESPEGUE porque el pronóstico es VENTISCA',
                        ["zona: {$datos['zona']}", "fecha: {$datos['fechaProgramada']}", 'pronostico: VENTISCA']
                    );
                }
            } catch (MeteoServiceException $e) {
                // Manejo de fallo distribuido (Escenario B): degradación elegante con 202 Accepted
                $estadoInicial = Mantenimiento::ESTADO_PENDIENTE_CLIMA;
                $statusHttp = 202;
                $aviso = 'Servicio meteorológico no disponible temporalmente. El mantenimiento quedó en estado PENDIENTE_CLIMA y se confirmará después.';
            }
        }

        $mantenimiento = $this->mantenimientoRepository->crear([
            'zona'             => $datos['zona'],
            'tipo'             => $datos['tipo'],
            'fecha_programada' => $datos['fechaProgramada'],
            'responsable'      => trim($datos['responsable']),
            'estado'           => $estadoInicial,
        ]);

        $payload = $mantenimiento->toArray();
        if ($aviso !== null) {
            $payload['mensaje'] = $aviso;
        }

        return [
            'status' => $statusHttp,
            'data'   => $payload,
        ];
    }

    /**
     * Regla 3: Transiciones válidas PROGRAMADO -> EN_CURSO | CANCELADO, EN_CURSO -> COMPLETADO.
     * Cualquier otra transición responde 409 Conflict.
     */
    public function cambiarEstado(int $id, string $nuevoEstado): Mantenimiento
    {
        $mantenimiento = $this->obtenerPorId($id);
        $estadoActual = $mantenimiento->estado;
        $permitidos = self::TRANSICIONES_VALIDAS[$estadoActual] ?? [];

        if (!in_array($nuevoEstado, $permitidos, true)) {
            throw new ApiException(
                409,
                'TRANSICION_INVALIDA',
                "No se puede pasar de {$estadoActual} a {$nuevoEstado}"
            );
        }

        return $this->mantenimientoRepository->actualizarEstado($mantenimiento, $nuevoEstado);
    }
}
