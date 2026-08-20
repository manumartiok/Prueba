<?php

namespace Database\Seeders;

use App\Models\Ubicacion;
use App\Models\Mesa;
use Illuminate\Database\Seeder;

class MesaSeeder extends Seeder
{
    /**
     * SUPUESTO: la cantidad y capacidad de mesas no viene definida en la consigna
     * (eso corresponde al ABM del punto 1, fuera de este alcance). Se cargan
     * mesas de ejemplo para poder probar la logica de disponibilidad y union
     * de mesas del punto 3.
     */
    public function run(): void
    {
        $mesasPorUbicacion = [
            'A' => [2, 2, 4, 4, 6],       // 5 mesas
            'B' => [2, 4, 4, 6, 2],       // 5 mesas
            'C' => [4, 4, 8],             // 3 mesas, capacidad mayor
            'D' => [2, 2, 4],             // 3 mesas
        ];

        foreach ($mesasPorUbicacion as $nombreUbicacion => $capacidades) {
            $ubicacion = Ubicacion::where('nombre', $nombreUbicacion)->firstOrFail();

            foreach ($capacidades as $numero => $capacidad) {
                Mesa::updateOrCreate(
                    [
                        'ubicacion_id' => $ubicacion->id,
                        'numero' => $numero + 1,
                    ],
                    ['capacidad' => $capacidad]
                );
            }
        }
    }
}
