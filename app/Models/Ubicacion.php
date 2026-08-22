<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ubicacion extends Model
{
    protected $fillable = ['nombre', 'orden'];

    public function mesas(): HasMany //1 ubicacion muchas mesas
    {
        return $this->hasMany(Mesa::class);
    }


    public static function enOrdenDePrioridad() // devuelve las mesas organizadas por el campo orden
    {
        return static::orderBy('orden')->get();
    }
}
