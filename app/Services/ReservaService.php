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
        $ordenadas = $mesasLibres->sortBy('capacidad')->values();

        // Probar primero con una sola mesa: la mas chica que alcance
        $mesaUnica = $ordenadas->first(fn ($mesa) => $mesa->capacidad >= $cantidadPersonas);
        if ($mesaUnica !== null) {
            return collect([$mesaUnica]);
        }

        // Si ninguna mesa sola alcanza, probar combinaciones de hasta 3 mesas,
        // empezando por las mas chicas (greedy ascendente) para minimizar
        // capacidad total desperdiciada.
        for ($cantidadMesas = 2; $cantidadMesas <= self::MAX_MESAS_POR_RESERVA; $cantidadMesas++) {
            $combinacion = $ordenadas->take($cantidadMesas);

            if ($combinacion->count() === $cantidadMesas
                && $combinacion->sum('capacidad') >= $cantidadPersonas) {
                return $combinacion;
            }
        }

        return null;
    }
}
