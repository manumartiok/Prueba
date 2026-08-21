<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Ubicacion;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservaService
{
    /**
     * Maximo de mesas que se pueden combinar en una sola reserva, segun consigna.
     */
    public const MAX_MESAS_POR_RESERVA = 3;

    public function __construct(
        private HorarioValidator $horarioValidator,
        private DisponibilidadService $disponibilidadService,
    ) {
    }

    /**
     * Crea una reserva: valida horario, busca ubicacion disponible en orden
     * de prioridad, arma la combinacion de mesas necesaria, y persiste todo
     * dentro de una transaccion para evitar condiciones de carrera.
     *
     * @param  array{fecha: string, hora_inicio: string, cantidad_personas: int, cliente_nombre?: string, cliente_telefono?: string}  $datos
     *
     * @throws \InvalidArgumentException  si el horario no es valido
     * @throws RuntimeException           si no hay disponibilidad en ninguna ubicacion
     */
    public function crear(array $datos): Reserva
    {
        $fechaHoraInicio = Carbon::parse($datos['fecha'].' '.$datos['hora_inicio']);

        // 1. Validar horario permitido + anticipacion minima
        $this->horarioValidator->validar($fechaHoraInicio);

        $horaInicio = $fechaHoraInicio->format('H:i:s');
        $horaFin = $fechaHoraInicio->copy()->addMinutes(HorarioValidator::DURACION_MINUTOS)->format('H:i:s');
        $cantidadPersonas = (int) $datos['cantidad_personas'];

        // 2. Recorrer ubicaciones en orden de prioridad y buscar una combinacion de mesas
        //    Se usa lockForUpdate dentro de una transaccion para evitar que dos
        //    requests simultaneos reserven la misma mesa (condicion de carrera).
        return DB::transaction(function () use ($datos, $fechaHoraInicio, $horaInicio, $horaFin, $cantidadPersonas) {
            foreach (Ubicacion::enOrdenDePrioridad() as $ubicacion) {
                $mesasLibres = $this->disponibilidadService->mesasLibres(
                    $ubicacion->id,
                    $fechaHoraInicio->toDateString(),
                    $horaInicio,
                    $horaFin
                );

                $combinacion = $this->buscarCombinacion($mesasLibres, $cantidadPersonas);

                if ($combinacion !== null) {
                    $reserva = Reserva::create([
                        'ubicacion_id' => $ubicacion->id,
                        'fecha' => $fechaHoraInicio->toDateString(),
                        'hora_inicio' => $horaInicio,
                        'hora_fin' => $horaFin,
                        'cantidad_personas' => $cantidadPersonas,
                        'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                        'cliente_telefono' => $datos['cliente_telefono'] ?? null,
                        'estado' => 'confirmada',
                    ]);

                    $reserva->mesas()->attach($combinacion->pluck('id'));

                    // 3. Invalidar cache: la proxima consulta de disponibilidad
                    //    para esta ubicacion+fecha debe reflejar la mesa recien ocupada.
                    $this->disponibilidadService->invalidar($ubicacion->id, $fechaHoraInicio->toDateString());

                    return $reserva->load('mesas', 'ubicacion');
                }
            }

            throw new RuntimeException('No hay disponibilidad para la fecha, hora y cantidad de personas solicitada.');
        });
    }

    /**
     * Busca la combinacion mas eficiente de mesas libres cuya capacidad
     * sumada alcance a la cantidad de personas, respetando el maximo de 3 mesas.
     *
     * Estrategia:
     * 1. Si una sola mesa alcanza, usar la MAS CHICA que alcance (no la mas
     *    grande disponible), para no desperdiciar mesas grandes en grupos
     *    chicos y dejarlas libres para reservas que si las necesiten.
     * 2. Si ninguna mesa sola alcanza, combinar de a 2 y despues de a 3,
     *    tambien priorizando las mesas mas chicas primero (greedy ascendente).
     *
     * SUPUESTO: no se exige que la capacidad combinada sea "exacta", alcanza
     * con que sea >= a la cantidad de personas pedida.
     */
private function buscarCombinacion(Collection $mesasLibres, int $cantidadPersonas): ?Collection
{
    $elementos = $mesasLibres->values()->all();
    $mejorCombinacion = null;
    $mejorDesperdicio = null;
    $mejorCantidadMesas = null;

    foreach ($this->generarSubconjuntos($elementos) as $combinacion) {
        $cantidadMesas = count($combinacion);

        if ($cantidadMesas === 0 || $cantidadMesas > self::MAX_MESAS_POR_RESERVA) {
            continue;
        }

        $suma = array_sum(array_map(fn ($mesa) => $mesa->capacidad, $combinacion));

        if ($suma < $cantidadPersonas) {
            continue;
        }

        $desperdicio = $suma - $cantidadPersonas;

        // Preferimos: menos mesas primero, y a igual cantidad de mesas, menor desperdicio.
        $esMejor = $mejorCombinacion === null
            || $cantidadMesas < $mejorCantidadMesas
            || ($cantidadMesas === $mejorCantidadMesas && $desperdicio < $mejorDesperdicio);

        if ($esMejor) {
            $mejorCombinacion = $combinacion;
            $mejorDesperdicio = $desperdicio;
            $mejorCantidadMesas = $cantidadMesas;
        }
    }

    return $mejorCombinacion !== null ? collect($mejorCombinacion) : null;
}

/**
 * Genera todos los subconjuntos (no vacios) de un array usando mascara de
 * bits: para N elementos hay 2^N - 1 subconjuntos posibles. Con la cantidad
 * de mesas por ubicacion que maneja este sistema (< 10), es instantaneo y
 * mucho mas simple/confiable que una recursion manual.
 */
private function generarSubconjuntos(array $elementos): \Generator
{
    $n = count($elementos);

    for ($mascara = 1; $mascara < (1 << $n); $mascara++) {
        $combinacion = [];
        for ($i = 0; $i < $n; $i++) {
            if ($mascara & (1 << $i)) {
                $combinacion[] = $elementos[$i];
            }
        }
        yield $combinacion;
    }
}



    /**
 * Actualiza una reserva existente: re-valida horario y disponibilidad
 * como si fuera una reserva nueva, liberando primero sus propias mesas
 * para no auto-bloquearse. Si no hay lugar para los nuevos datos, se
 * revierte todo (transaccion) y la reserva original queda sin tocar.
 */
public function actualizar(Reserva $reserva, array $datos): Reserva
{
    $fechaHoraInicio = Carbon::parse($datos['fecha'].' '.$datos['hora_inicio']);

    $this->horarioValidator->validar($fechaHoraInicio);

    $horaInicio = $fechaHoraInicio->format('H:i:s');
    $horaFin = $fechaHoraInicio->copy()->addMinutes(HorarioValidator::DURACION_MINUTOS)->format('H:i:s');
    $cantidadPersonas = (int) $datos['cantidad_personas'];

    return DB::transaction(function () use ($reserva, $datos, $fechaHoraInicio, $horaInicio, $horaFin, $cantidadPersonas) {
        $ubicacionIdVieja = $reserva->ubicacion_id;
        $fechaVieja = $reserva->fecha->toDateString();

        // Libera las mesas actuales ANTES de buscar, para que la propia
        // reserva no aparezca como "ocupante" de si misma al recalcular.
        $reserva->mesas()->detach();
        $this->disponibilidadService->invalidar($ubicacionIdVieja, $fechaVieja);

        foreach (Ubicacion::enOrdenDePrioridad() as $ubicacion) {
            $mesasLibres = $this->disponibilidadService->mesasLibres(
                $ubicacion->id,
                $fechaHoraInicio->toDateString(),
                $horaInicio,
                $horaFin
            );

            $combinacion = $this->buscarCombinacion($mesasLibres, $cantidadPersonas);

            if ($combinacion !== null) {
                $reserva->update([
                    'ubicacion_id' => $ubicacion->id,
                    'fecha' => $fechaHoraInicio->toDateString(),
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'cantidad_personas' => $cantidadPersonas,
                    'cliente_nombre' => $datos['cliente_nombre'] ?? null,
                    'cliente_telefono' => $datos['cliente_telefono'] ?? null,
                ]);

                $reserva->mesas()->attach($combinacion->pluck('id'));
                $this->disponibilidadService->invalidar($ubicacion->id, $fechaHoraInicio->toDateString());

                return $reserva->fresh(['mesas', 'ubicacion']);
            }
        }

        // Si no se encuentra lugar, la excepcion revierte la transaccion
        // completa (incluido el detach de arriba): la reserva original queda intacta.
        throw new RuntimeException('No hay disponibilidad para el nuevo horario/cantidad de personas solicitada.');
    });
}
}
