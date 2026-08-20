<?php

namespace App\Services;

use App\Models\Mesa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class DisponibilidadService
{
    /**
     * TTL corto: el cache es solo para evitar pegarle a la DB en cada
     * request mientras se resuelve una reserva (varias consultas seguidas
     * en el mismo ciclo de armado). Si vence, se reconstruye solo desde DB.
     */
    private const TTL_SEGUNDOS = 60;

    /**
     * Devuelve, para una ubicacion y fecha dadas, TODAS las reservas de ese
     * dia (mesa_id, hora_inicio, hora_fin) leyendo primero del cache en
     * memoria. Esto es lo que el enunciado pide como "cache en memoria de
     * la disponibilidad por ubicacion": en vez de consultar la DB cada vez
     * que se quiere saber si una mesa esta libre, se trae UNA vez el mapa
     * de ocupacion del dia y se reutiliza en memoria durante el TTL.
     */
    private function reservasDelDia(int $ubicacionId, string $fecha): Collection
    {
        $clave = $this->claveCache($ubicacionId, $fecha);

        return Cache::remember($clave, self::TTL_SEGUNDOS, function () use ($ubicacionId, $fecha) {
            return \App\Models\Reserva::query()
                ->where('ubicacion_id', $ubicacionId)
                ->where('fecha', $fecha)
                ->where('estado', 'confirmada')
                ->with('mesas:id') // solo traigo los ids de mesa, no hace falta mas
                ->get(['id', 'hora_inicio', 'hora_fin'])
                ->map(fn ($reserva) => [
                    'mesa_ids' => $reserva->mesas->pluck('id')->all(),
                    'hora_inicio' => $reserva->hora_inicio,
                    'hora_fin' => $reserva->hora_fin,
                ]);
        });
    }

    /**
     * Mesas de una ubicacion que estan libres para el rango horario pedido.
     * Se calcula: (todas las mesas de la ubicacion) - (mesas cuya reserva
     * se solapa con el rango pedido).
     */
    public function mesasLibres(int $ubicacionId, string $fecha, string $horaInicio, string $horaFin): Collection
    {
        $todasLasMesas = Mesa::where('ubicacion_id', $ubicacionId)->get();
        $reservasDelDia = $this->reservasDelDia($ubicacionId, $fecha);

        $mesaIdsOcupadas = $reservasDelDia
            ->filter(fn ($reserva) => $this->seSolapan(
                $reserva['hora_inicio'], $reserva['hora_fin'],
                $horaInicio, $horaFin
            ))
            ->flatMap(fn ($reserva) => $reserva['mesa_ids'])
            ->unique();

        return $todasLasMesas->reject(
            fn (Mesa $mesa) => $mesaIdsOcupadas->contains($mesa->id)
        )->values();
    }

    /**
     * Dos rangos horarios se solapan si uno empieza antes de que el otro
     * termine, en ambas direcciones. Es la formula estandar de overlap.
     */
    private function seSolapan(string $inicioA, string $finA, string $inicioB, string $finB): bool
    {
        return $inicioA < $finB && $inicioB < $finA;
    }

    /**
     * Invalida el cache de una ubicacion+fecha. Hay que llamarlo cada vez
     * que se confirma (o cancela) una reserva, para que la proxima lectura
     * refleje el nuevo estado en vez de servir datos vencidos del cache.
     */
    public function invalidar(int $ubicacionId, string $fecha): void
    {
        Cache::forget($this->claveCache($ubicacionId, $fecha));
    }

    private function claveCache(int $ubicacionId, string $fecha): string
    {
        return "disponibilidad:ubicacion:{$ubicacionId}:fecha:{$fecha}";
    }
}
