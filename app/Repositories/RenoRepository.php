<?php

namespace App\Repositories;

use App\Models\Reno;
use Illuminate\Database\Eloquent\Collection;

/**
 * Capa de datos para el recurso Reno.
 * Aísla las consultas Eloquent/MySQL de la capa de servicios.
 */
class RenoRepository
{
    public function listar(?string $estado = null): Collection
    {
        $query = Reno::query()->orderBy('id');

        if ($estado !== null && $estado !== '') {
            $query->where('estado', $estado);
        }

        return $query->get();
    }

    public function buscarPorId(int $id): ?Reno
    {
        return Reno::find($id);
    }

    public function existePorNombre(string $nombre, ?int $excluirId = null): bool
    {
        $query = Reno::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($nombre))]);

        if ($excluirId !== null) {
            $query->where('id', '!=', $excluirId);
        }

        return $query->exists();
    }

    public function crear(array $datos): Reno
    {
        return Reno::create($datos);
    }

    public function actualizar(Reno $reno, array $datos): Reno
    {
        $reno->update($datos);

        return $reno->fresh();
    }

    public function eliminar(Reno $reno): void
    {
        $reno->delete();
    }

    public function contarAsignadosAlVuelo(): int
    {
        return Reno::where('estado', Reno::ESTADO_ASIGNADO_VUELO)->count();
    }

    public function obtenerAsignadosAlVuelo(): Collection
    {
        return Reno::where('estado', Reno::ESTADO_ASIGNADO_VUELO)
            ->orderBy('id')
            ->get();
    }
}
