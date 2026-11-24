<?php

namespace App\Repositories;

use App\Models\Mantenimiento;
use Illuminate\Database\Eloquent\Collection;

/**
 * Capa de datos para el recurso Mantenimiento.
 * Aísla las consultas Eloquent/MySQL de la capa de servicios.
 */
class MantenimientoRepository
{
    public function listar(?string $zona = null): Collection
    {
        $query = Mantenimiento::query()->orderBy('id');

        if ($zona !== null && $zona !== '') {
            $query->where('zona', $zona);
        }

        return $query->get();
    }

    public function buscarPorId(int $id): ?Mantenimiento
    {
        return Mantenimiento::find($id);
    }

    public function crear(array $datos): Mantenimiento
    {
        return Mantenimiento::create($datos);
    }

    public function actualizarEstado(Mantenimiento $mantenimiento, string $nuevoEstado): Mantenimiento
    {
        $mantenimiento->estado = $nuevoEstado;
        $mantenimiento->save();

        return $mantenimiento->fresh();
    }

    public function existePistaCompletada(): bool
    {
        return Mantenimiento::where('zona', Mantenimiento::ZONA_PISTA_DESPEGUE)
            ->where('estado', Mantenimiento::ESTADO_COMPLETADO)
            ->exists();
    }
}
