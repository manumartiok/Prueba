<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ubicacion_id')->constrained('ubicacions');

            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin'); // hora_inicio + 2hs, se calcula al crear

            $table->unsignedTinyInteger('cantidad_personas');
            $table->string('cliente_nombre')->nullable();
            $table->string('cliente_telefono')->nullable();

            $table->enum('estado', ['confirmada', 'cancelada'])->default('confirmada');

            $table->timestamps();

            // esta es la query mas frecuente: "dame reservas de esta fecha en esta ubicacion"
            $table->index(['fecha', 'ubicacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
