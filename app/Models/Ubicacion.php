<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $fillable = ['nombre', 'orden'];

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class);
    }

    /**
     * Devuelve las ubicaciones ordenadas por prioridad de asignacion (A primero, etc).
     */
    public static function enOrdenDePrioridad()
    {
        return static::orderBy('orden')->get();
    }
}
