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

    protected $casts = [ //cast convierte fecha en objeto Carbon
        'fecha' => 'date',
    ];

    public function ubicacion(): BelongsTo  //cada mesa 1 ubicacion
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function mesas(): BelongsToMany //1 reserva muchas mesas
    {
        return $this->belongsToMany(Mesa::class, 'reserva_mesa');
    }
}
