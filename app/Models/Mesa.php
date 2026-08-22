<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Mesa extends Model
{
    protected $fillable = ['ubicacion_id', 'numero', 'capacidad']; //fillable para asignaciones masivas y permiso de datos

    public function ubicacion(): BelongsTo //cada mesa 1 ubicacion
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function reservas(): BelongsToMany //1 mesa en muchas reservas
    {
        return $this->belongsToMany(Reserva::class, 'reserva_mesa');
    }
}
