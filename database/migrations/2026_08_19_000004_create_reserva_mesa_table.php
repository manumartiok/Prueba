<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tabla pivote entre reservas y mesas
        Schema::create('reserva_mesa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->constrained('reservas')->cascadeOnDelete();
            $table->foreignId('mesa_id')->constrained('mesas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['reserva_id', 'mesa_id']); //reserva unica por mesa
            
            $table->index('mesa_id'); //para encontrar rapido la mesa en la base de datos
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_mesa');
    }
};
