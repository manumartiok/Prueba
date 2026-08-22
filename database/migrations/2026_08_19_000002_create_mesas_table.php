<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mesas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ubicacion_id')->constrained('ubicacions')->cascadeOnDelete();
            $table->unsignedInteger('numero'); //numero de mesas en ubicaciones, Integer para maximo de 255 caracteres
            $table->unsignedTinyInteger('capacidad'); // cantidad de personas por mesas TinyInteger, muchos caracteres
            $table->timestamps();

            // no puede haber dos mesas con el mismo numero en la misma ubicacion
            $table->unique(['ubicacion_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesas');
    }
};
