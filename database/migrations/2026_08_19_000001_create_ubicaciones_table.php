<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ubicacions', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 1); // 'A', 'B', 'C', 'D'
            $table->unsignedTinyInteger('orden'); // define prioridad de asignación (1 = primero)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicacions');
    }
};
