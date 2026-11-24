<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reno extends Model
{
    public const ESTADO_DISPONIBLE     = 'DISPONIBLE';
    public const ESTADO_EN_DESCANSO    = 'EN_DESCANSO';
    public const ESTADO_EN_REVISION    = 'EN_REVISION';
    public const ESTADO_ASIGNADO_VUELO = 'ASIGNADO_VUELO';

    public const ESTADOS_VALIDOS = [
        self::ESTADO_DISPONIBLE,
        self::ESTADO_EN_DESCANSO,
        self::ESTADO_EN_REVISION,
        self::ESTADO_ASIGNADO_VUELO,
    ];

    public const MAX_RENOS_VUELO   = 9;
    public const ENERGIA_MIN_VUELO = 70;

    protected $table = 'renos';

    protected $fillable = [
        'nombre',
        'edad',
        'nivel_energia',
        'estado',
    ];

    protected $casts = [
        'edad'          => 'integer',
        'nivel_energia' => 'integer',
    ];

    /**
     * Devuelve el formato JSON exacto definido en la especificación del Escenario B.
     */
    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'nombre'       => $this->nombre,
            'edad'         => (int) $this->edad,
            'nivelEnergia' => (int) $this->nivel_energia,
            'estado'       => $this->estado,
        ];
    }
}
