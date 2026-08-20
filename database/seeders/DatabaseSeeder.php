<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orquesta la carga de datos iniciales del proyecto.
     * Orden importante: las ubicaciones tienen que existir ANTES que las
     * mesas, porque cada mesa depende de un ubicacion_id.
     */
    public function run(): void
    {
        $this->call([
            UbicacionSeeder::class,
            MesaSeeder::class,
        ]);
    }
}
