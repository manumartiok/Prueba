<?php

namespace Database\Seeders;

use App\Models\Ubicacion;
use Illuminate\Database\Seeder;

class UbicacionSeeder extends Seeder
{
    /**
     * Carga las 4 ubicaciones fijas del local, con su orden de prioridad
     * para la asignacion automatica (A se intenta primero, despues B, etc).
     */
    public function run(): void
    {
        $ubicaciones = [
            ['nombre' => 'A', 'orden' => 1],
            ['nombre' => 'B', 'orden' => 2],
            ['nombre' => 'C', 'orden' => 3],
            ['nombre' => 'D', 'orden' => 4],
        ];

        foreach ($ubicaciones as $u) {
            Ubicacion::updateOrCreate(['nombre' => $u['nombre']], $u);
        }
    }
}
