<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pivote: una reserva puede tener 1 a 3 mesas (union de mesas)
        Schema::create('reserva_mesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reserva_id', 'mesa_id']);
            // clave para detectar solapamientos rapido por mesa
            $table->index('mesa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_mesa');
    }
};
