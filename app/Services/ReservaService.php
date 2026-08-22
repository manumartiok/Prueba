<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\Ubicacion;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservaService
{

    //maximo de mesas por reserva
    public const MAX_MESAS_POR_RESERVA = 3;

    //trae los services para usar sus funciones
    public function __construct(
        private HorarioValidator $horarioValidator,
        private DisponibilidadService $disponibilidadService,
    ) {

    }

    //metodo para crear la reserva
    public function crear(array $datos): Reserva
    {
        $fechaHoraInicio = Carbon::parse($datos['fecha'].' '.$datos['hora_inicio']); //construye la fecha y hora de la reserva

        //usa el service para validar la hora
        $this->horarioValidator->validar($fechaHoraInicio);

        //obtenes y formatear datos
        $horaInicio = $fechaHoraInicio->format('H:i:s');
        $horaFin = $this->horarioValidator->calcularHoraFin($fechaHoraInicio)->format('H:i:s');
        $cantidadPersonas = (int) $datos['cantidad_personas']; //convierte el dato a un valor entero "5" a 5 ejemplo


        //transaccion de datos
        return DB::transaction(function () use ($datos, $fechaHoraInicio, $horaInicio, $horaFin, $cantidadPersonas) {
            //empieza el bucle devolviendo las ubicaciones con la funcion de orden
            foreach (Ubicacion::enOrdenDePrioridad() as $ubicacion) {
                $mesasLibres = $this->disponibilidadService->mesasLibres(//usa el service de disponibilidad
                    $ubicacion->id,
                    $fechaHoraInicio->toDateString(),
                    $horaInicio,
                    $horaFin
                );

                $combinacion = $this->buscarCombinacion($mesasLibres, $cantidadPersonas); //busca la combinacion entre mesas disponibles y personas

                if ($combinacion !== null) {//si encunetra una combinacion, crea la reserva y la guarda en la DB
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

                    $reserva->mesas()->attach($combinacion->pluck('id'));//attach crea el registro  en la tabla pivote

                    //se invalida la cache anterior
                    $this->disponibilidadService->invalidar($ubicacion->id, $fechaHoraInicio->toDateString());

                    return $reserva->load('mesas', 'ubicacion');
                }
            }

            throw new RuntimeException('No hay disponibilidad para la fecha, hora y cantidad de personas solicitada.');
        });
    }

    //busca la combinacion, primero buscando la mesa mas chica que alcance, despues con 2 y despues con 3, sin cantidad exacta, solo que alcance para la cantidad de personas
private function buscarCombinacion(Collection $mesasLibres, int $cantidadPersonas): ?Collection //?devuelve Collection si encuentra combinacion, y si no null
{
    $elementos = $mesasLibres->values()->all();//reindexa y devuelva la combinacion en un array
    $mejorCombinacion = null;
    $mejorDesperdicio = null;
    $mejorCantidadMesas = null;

    //bucle que genera combinaciones
    foreach ($this->generarSubconjuntos($elementos) as $combinacion) {
        $cantidadMesas = count($combinacion);//cuenta la cantidad de combinaciones 

        //solo acepta la combinacion si es distinta a 0 y menor a la constante de mesas
        if ($cantidadMesas === 0 || $cantidadMesas > self::MAX_MESAS_POR_RESERVA) {
            continue;
        }

        //trae la capacidad de lsa mesas y las suma
        $suma = array_sum(array_map(fn ($mesa) => $mesa->capacidad, $combinacion));

        //dice si la capacidad alcanza para las personas
        if ($suma < $cantidadPersonas) {
            continue;
        }


        $desperdicio = $suma - $cantidadPersonas;

        //busca la mejor combinacion, primero usando la menores mesas, y a igualdad, con menor desperdicio
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


//todas las combinaciones posibles de mesa
private function generarSubconjuntos(array $elementos): \Generator //Generator hace que se genere las combinaciones 1 a 1 en la memoria
{
    $n = count($elementos);//cuenta cuantos elementos hay en el array

    //usa bits para generar todas las combinaciones  mesa 1 001 mesa 2 010 mesa 3 100 mesa 1 + 2 + 3 111 1 + 2 011, etc
    for ($mascara = 1; $mascara < (1 << $n); $mascara++) { // 1 << $n usa el valor a binario
        $combinacion = [];
        for ($i = 0; $i < $n; $i++) {
            if ($mascara & (1 << $i)) {
                $combinacion[] = $elementos[$i];
            }
        }
        yield $combinacion; //yield hace que la funcion sea un generador
    }
}

    //cuando se edita una reserva para actualizarla
public function actualizar(Reserva $reserva, array $datos): Reserva
{
    $fechaHoraInicio = Carbon::parse($datos['fecha'].' '.$datos['hora_inicio']);

    $this->horarioValidator->validar($fechaHoraInicio);

    $horaInicio = $fechaHoraInicio->format('H:i:s');
    $horaFin = $this->horarioValidator->calcularHoraFin($fechaHoraInicio)->format('H:i:s');
    $cantidadPersonas = (int) $datos['cantidad_personas'];

    //todo el cambio se hace en 1 transaccion, para que si algo falla, la reserva quede como la original
    return DB::transaction(function () use ($reserva, $datos, $fechaHoraInicio, $horaInicio, $horaFin, $cantidadPersonas) {
        //se guardan los antiguos datos para invalidar la cache a futuro
        $ubicacionIdVieja = $reserva->ubicacion_id;
        $fechaVieja = $reserva->fecha->toDateString();

        
        //elimina la relacion a la mesa de la reserva original e invalida la cache
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

                return $reserva->fresh(['mesas', 'ubicacion']);//fresh vuelve a cargar los datos a la DB
            }
        }

        throw new RuntimeException('No hay disponibilidad para el nuevo horario/cantidad de personas solicitada.');
    });
}
}
