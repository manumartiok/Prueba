<?php

use App\Http\Controllers\Api\ReservaController;
use Illuminate\Support\Facades\Route;

Route::prefix('reservas')->group(function () {
    Route::get('/', [ReservaController::class, 'index']);   // GET /api/reservas?fecha=2026-08-24  (punto 4)
    Route::post('/', [ReservaController::class, 'store']);  // POST /api/reservas                  (punto 3)
});

// GET /api/disponibilidad?fecha=2026-08-24&hora_inicio=14:00  (auxiliar para el grid del frontend)
Route::get('disponibilidad', [ReservaController::class, 'disponibilidad']);
