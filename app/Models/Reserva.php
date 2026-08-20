<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reserva extends Model
{
    protected $fillable = [
        'ubicacion_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'cantidad_personas',
        'cliente_nombre',
        'cliente_telefono',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function mesas(): BelongsToMany
    {
        return $this->belongsToMany(Mesa::class, 'reserva_mesa');
    }
}
