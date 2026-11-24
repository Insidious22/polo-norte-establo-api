<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Reno;
use App\Repositories\RenoRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Capa de servicios para Renos.
 * Aquí viven las reglas de negocio 1, 2 y 5 (nunca en el controlador).
 */
class RenoService
{
    public function __construct(
        private readonly RenoRepository $renoRepository
    ) {}

    public function listar(?string $estado = null): Collection
    {
        if ($estado !== null && $estado !== '' && !in_array($estado, Reno::ESTADOS_VALIDOS, true)) {
            throw new ApiException(
                400,
                'FILTRO_ESTADO_INVALIDO',
                'El filtro estado no es válido',
                ['Estados permitidos: ' . implode(', ', Reno::ESTADOS_VALIDOS)]
            );
        }

        return $this->renoRepository->listar($estado);
    }

    public function obtenerPorId(int $id): Reno
    {
        $reno = $this->renoRepository->buscarPorId($id);

        if (!$reno) {
            throw new ApiException(
                404,
                'RENO_NO_ENCONTRADO',
                "No existe un reno con el id {$id}"
            );
        }

        return $reno;
    }

    public function crear(array $datos): Reno
    {
        $nombre = trim($datos['nombre']);

        if ($this->renoRepository->existePorNombre($nombre)) {
            throw new ApiException(
                409,
                'NOMBRE_DUPLICADO',
                "Ya existe un reno registrado con el nombre '{$nombre}'"
            );
        }

        $estado = $datos['estado'] ?? Reno::ESTADO_DISPONIBLE;

        if ($estado === Reno::ESTADO_ASIGNADO_VUELO) {
            throw new ApiException(
                409,
                'ESTADO_INICIAL_INVALIDO',
                'Un reno nuevo no puede crearse directamente en ASIGNADO_VUELO. Regístralo como DISPONIBLE y usa PATCH /api/renos/{id}/asignacion'
            );
        }

        return $this->renoRepository->crear([
            'nombre'        => $nombre,
            'edad'          => (int) $datos['edad'],
            'nivel_energia' => (int) $datos['nivelEnergia'],
            'estado'        => $estado,
        ]);
    }

    public function actualizar(int $id, array $datos): Reno
    {
        $reno = $this->obtenerPorId($id);
        $nombre = trim($datos['nombre']);

        if ($this->renoRepository->existePorNombre($nombre, $id)) {
            throw new ApiException(
                409,
                'NOMBRE_DUPLICADO',
                "Ya existe otro reno registrado con el nombre '{$nombre}'"
            );
        }

        $nuevoEstado = $datos['estado'] ?? $reno->estado;
        $nuevaEnergia = (int) $datos['nivelEnergia'];

        // Evita saltar las reglas 1 y 2 intentando pasar a ASIGNADO_VUELO desde PUT
        if ($nuevoEstado === Reno::ESTADO_ASIGNADO_VUELO && $reno->estado !== Reno::ESTADO_ASIGNADO_VUELO) {
            $this->validarReglasAsignacion($reno->estado, $nuevaEnergia);
        }

        return $this->renoRepository->actualizar($reno, [
            'nombre'        => $nombre,
            'edad'          => (int) $datos['edad'],
            'nivel_energia' => $nuevaEnergia,
            'estado'        => $nuevoEstado,
        ]);
    }

    /**
     * Regla 5: No se puede eliminar un reno en estado ASIGNADO_VUELO.
     */
    public function eliminar(int $id): void
    {
        $reno = $this->obtenerPorId($id);

        if ($reno->estado === Reno::ESTADO_ASIGNADO_VUELO) {
            throw new ApiException(
                409,
                'RENO_ASIGNADO_NO_ELIMINABLE',
                "No se puede eliminar al reno '{$reno->nombre}' porque se encuentra en estado ASIGNADO_VUELO"
            );
        }

        $this->renoRepository->eliminar($reno);
    }

    /**
     * Reglas 1 y 2:
     * - Regla 1: Solo pasa a ASIGNADO_VUELO si nivelEnergia >= 70 y estado === DISPONIBLE (si no, 409).
     * - Regla 2: Máximo 9 renos asignados al vuelo (el décimo responde 409).
     */
    public function asignarAlVuelo(int $id): Reno
    {
        $reno = $this->obtenerPorId($id);

        $this->validarReglasAsignacion($reno->estado, (int) $reno->nivel_energia);

        return $this->renoRepository->actualizar($reno, [
            'estado' => Reno::ESTADO_ASIGNADO_VUELO,
        ]);
    }

    private function validarReglasAsignacion(string $estadoActual, int $nivelEnergia): void
    {
        if ($estadoActual !== Reno::ESTADO_DISPONIBLE) {
            throw new ApiException(
                409,
                'ESTADO_NO_DISPONIBLE',
                "El reno debe estar en estado DISPONIBLE para asignarse al vuelo (estado actual: {$estadoActual})"
            );
        }

        if ($nivelEnergia < Reno::ENERGIA_MIN_VUELO) {
            throw new ApiException(
                409,
                'ENERGIA_INSUFICIENTE',
                "El reno necesita un nivelEnergia mínimo de " . Reno::ENERGIA_MIN_VUELO . " para volar (actual: {$nivelEnergia})"
            );
        }

        $asignados = $this->renoRepository->contarAsignadosAlVuelo();
        if ($asignados >= Reno::MAX_RENOS_VUELO) {
            throw new ApiException(
                409,
                'CUPO_VUELO_COMPLETO',
                'Ya existen ' . Reno::MAX_RENOS_VUELO . ' renos asignados al vuelo; no se puede asignar un décimo reno'
            );
        }
    }
}
