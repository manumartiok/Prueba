<?php

namespace App\Services;

use App\Models\Mesa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class DisponibilidadService
{
    //constante que define el tiempo que vivira el dato en el cache (TTL = Time to Live)
    private const TTL_SEGUNDOS = 60;

    // devuelve todas las reservas de esa fecha, guardando el mapa de ocupacion en la memoria con 
    // la constante TTL y reutilizandolo en lugar de consultar a la DB
    private function reservasDelDia(int $ubicacionId, string $fecha): Collection
    {
        $clave = $this->claveCache($ubicacionId, $fecha); //llama a claveCache con ubicacion y fecha

        return Cache::remember($clave, self::TTL_SEGUNDOS, function () use ($ubicacionId, $fecha) { //con Cache:: revisa si existe el dato en cacho, en caso de que no, lo busca en la DB y ya queda guardado
           //hace una consulta al modelo Reserva, filtrando por ubicacion, fecha, estado (que este confirmado), con las mesas asociadas a cada reserva
            return \App\Models\Reserva::query()
                ->where('ubicacion_id', $ubicacionId)
                ->where('fecha', $fecha)
                ->where('estado', 'confirmada')
                ->with('mesas:id')
                ->get(['id', 'hora_inicio', 'hora_fin']) //dice que datos traer al ejecutar la consulta
                ->map(fn ($reserva) => [ //un mapeo para recorrer cada reserva y transformar su estructura
                    'mesa_ids' => $reserva->mesas->pluck('id')->all(),
                    'hora_inicio' => $reserva->hora_inicio,
                    'hora_fin' => $reserva->hora_fin,
                ]);
        });
    }

    //calcula que mesas estan ocupadas en el horario que se quiere reservar y las rechaza del total
    public function mesasLibres(int $ubicacionId, string $fecha, string $horaInicio, string $horaFin): Collection
{
    $todasLasMesas = Mesa::where('ubicacion_id', $ubicacionId)->get(); //busca las mesas de la ubicacion pedida
    $reservasDelDia = $this->reservasDelDia($ubicacionId, $fecha); //llama la funcion privada anterior

    $mesaIdsOcupadas = $reservasDelDia
        ->filter(fn ($reserva) => $this->seSolapan( //filtra por las mesas ocupadas usando la funcion seSopalan()
            $reserva['hora_inicio'], $reserva['hora_fin'],
            $horaInicio, $horaFin
        ))
        ->flatMap(fn ($reserva) => $reserva['mesa_ids'])//flatmap junta los ids
        ->unique();//elimina duplicados, para que solo aparezca 1 vez el id de la mesa ocupada

    $libres = $todasLasMesas->reject(//rechaza las ids de la mesa ocupada y reindexa los valores devueltos
        fn (Mesa $mesa) => $mesaIdsOcupadas->contains($mesa->id)
    )->values();

    return $libres;
}

    //funcion para determinar si se sobrepone el horario de una reserva con otra, A seria la reserva existente y B la que se desea crear
    private function seSolapan(string $inicioA, string $finA, string $inicioB, string $finB): bool //bool para que devuelva verdadero o falso
{
    //valores para manejar las reservas pasadas la media noche
    if ($finA <= $inicioA) $finA = '24:00:00';
    if ($finB <= $inicioB) $finB = '24:00:00';

    return $inicioA < $finB && $inicioB < $finA;
}

    // invalidamos la cache para evitar que al hacer una reserva, tome la cache vieja y una mesa ocupada la pueda tomar como libre
    public function invalidar(int $ubicacionId, string $fecha): void
    {
        Cache::forget($this->claveCache($ubicacionId, $fecha));
    }

    //construye las ID que va a guardar en cache
    private function claveCache(int $ubicacionId, string $fecha): string
    {
        return "disponibilidad:ubicacion:{$ubicacionId}:fecha:{$fecha}";
    }
}
