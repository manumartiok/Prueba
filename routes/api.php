<?php

use App\Http\Controllers\Api\ReservaController;
use Illuminate\Support\Facades\Route;

Route::prefix('reservas')->group(function () {
    Route::get('/', [ReservaController::class, 'index']); //ruta que obtiene los datos del listado de reservas
    Route::post('/', [ReservaController::class, 'store']);  //ruta que crea una nueva reserva
    Route::put('/{reserva}', [ReservaController::class, 'update']); //ruta que edita una reserva llamandola por id
    Route::delete('/{reserva}', [ReservaController::class, 'destroy']); //ruta que elimina una reserva
});

Route::get('disponibilidad', [ReservaController::class, 'disponibilidad']); //ruta para mostrar las mesas disponibles
