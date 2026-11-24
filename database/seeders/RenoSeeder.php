<?php

namespace Database\Seeders;

use App\Models\Reno;
use Illuminate\Database\Seeder;

class RenoSeeder extends Seeder
{
    /**
     * Precarga 8 renos clásicos asignados al vuelo + 2 renos de prueba
     * (uno con energía baja y otro en descanso) para probar fácilmente las reglas 1, 2 y 5.
     */
    public function run(): void
    {
        $renos = [
            ['nombre' => 'Dasher',   'edad' => 8,  'nivel_energia' => 92, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Dancer',   'edad' => 7,  'nivel_energia' => 88, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Prancer',  'edad' => 9,  'nivel_energia' => 85, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Vixen',    'edad' => 6,  'nivel_energia' => 90, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Comet',    'edad' => 10, 'nivel_energia' => 81, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Cupid',    'edad' => 5,  'nivel_energia' => 87, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Donner',   'edad' => 11, 'nivel_energia' => 79, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            ['nombre' => 'Blitzen',  'edad' => 9,  'nivel_energia' => 84, 'estado' => Reno::ESTADO_ASIGNADO_VUELO],
            // Renos de prueba para Regla 1 (energía < 70 y estado != DISPONIBLE)
            ['nombre' => 'Trueno',   'edad' => 4,  'nivel_energia' => 55, 'estado' => Reno::ESTADO_DISPONIBLE],
            ['nombre' => 'Estrella', 'edad' => 6,  'nivel_energia' => 95, 'estado' => Reno::ESTADO_EN_DESCANSO],
        ];

        foreach ($renos as $reno) {
            Reno::firstOrCreate(['nombre' => $reno['nombre']], $reno);
        }
    }
}
