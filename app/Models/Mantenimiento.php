<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model
{
    public const ZONA_PISTA_DESPEGUE = 'PISTA_DESPEGUE';
    public const ZONA_ESTABLO        = 'ESTABLO';
    public const ZONA_BODEGA_REGALOS = 'BODEGA_REGALOS';
    public const ZONA_HANGAR_TRINEO  = 'HANGAR_TRINEO';

    public const ZONAS_VALIDAS = [
        self::ZONA_PISTA_DESPEGUE,
        self::ZONA_ESTABLO,
        self::ZONA_BODEGA_REGALOS,
        self::ZONA_HANGAR_TRINEO,
    ];

    public const TIPO_LIMPIEZA_NIEVE = 'LIMPIEZA_NIEVE';
    public const TIPO_REPARACION     = 'REPARACION';
    public const TIPO_INSPECCION     = 'INSPECCION';

    public const TIPOS_VALIDOS = [
        self::TIPO_LIMPIEZA_NIEVE,
        self::TIPO_REPARACION,
        self::TIPO_INSPECCION,
    ];

    public const ESTADO_PROGRAMADO      = 'PROGRAMADO';
    public const ESTADO_EN_CURSO        = 'EN_CURSO';
    public const ESTADO_COMPLETADO      = 'COMPLETADO';
    public const ESTADO_CANCELADO       = 'CANCELADO';
    public const ESTADO_PENDIENTE_CLIMA = 'PENDIENTE_CLIMA';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_PROGRAMADO,
        self::ESTADO_EN_CURSO,
        self::ESTADO_COMPLETADO,
        self::ESTADO_CANCELADO,
        self::ESTADO_PENDIENTE_CLIMA,
    ];

    protected $table = 'mantenimientos';

    protected $fillable = [
        'zona',
        'tipo',
        'fecha_programada',
        'responsable',
        'estado',
    ];

    /**
     * Devuelve el formato JSON exacto definido en la especificación del Escenario B.
     */
    public function toArray(): array
    {
        $fecha = $this->fecha_programada instanceof CarbonInterface
            ? $this->fecha_programada->format('Y-m-d')
            : substr((string) $this->fecha_programada, 0, 10);

        return [
            'id'              => $this->id,
            'zona'            => $this->zona,
            'tipo'            => $this->tipo,
            'fechaProgramada' => $fecha,
            'responsable'     => $this->responsable,
            'estado'          => $this->estado,
        ];
    }
}
