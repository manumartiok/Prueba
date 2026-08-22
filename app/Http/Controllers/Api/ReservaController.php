<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservaRequest;
use App\Services\ReservaService;
use App\Services\DisponibilidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use App\Models\Reserva;


class ReservaController extends Controller
{
    public function __construct(
    private ReservaService $reservaService,
    private DisponibilidadService $disponibilidadService,
) {
}

//funcion para actualizar una reserva
public function update(ReservaRequest $request, Reserva $reserva): JsonResponse
{
    //usa el service
    try {
        $actualizada = $this->reservaService->actualizar($reserva, $request->validated());//validated hace que los datos que pasen sean los de ReservaRequest
    } catch (InvalidArgumentException $e) {
        return response()->json(['message' => $e->getMessage()], 422);
    } catch (RuntimeException $e) {
        return response()->json(['message' => $e->getMessage()], 409);
    }

    //retorna un json
    return response()->json([
        'message' => 'Reserva actualizada.',
        'reserva' => [
            'id' => $actualizada->id,
            'fecha' => $actualizada->fecha->toDateString(),
            'hora_inicio' => $actualizada->hora_inicio,
            'hora_fin' => $actualizada->hora_fin,
            'ubicacion' => $actualizada->ubicacion->nombre,
            'mesas' => $actualizada->mesas->pluck('numero'),
            'cantidad_personas' => $actualizada->cantidad_personas,
        ],
    ]);
}

//elimina una reserva, guarda los datos para invalidar la cache
public function destroy(Reserva $reserva): JsonResponse
{
    $ubicacionId = $reserva->ubicacion_id;
    $fecha = $reserva->fecha->toDateString();

    $reserva->mesas()->detach();
    $reserva->delete();

    $this->disponibilidadService->invalidar($ubicacionId, $fecha);

    return response()->json(['message' => 'Reserva eliminada.']);
}

    //crear una reserva
    public function store(ReservaRequest $request): JsonResponse
    {
        try {
            $reserva = $this->reservaService->crear($request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Reserva confirmada.',
            'reserva' => [
                'id' => $reserva->id,
                'fecha' => $reserva->fecha->toDateString(),
                'hora_inicio' => $reserva->hora_inicio,
                'hora_fin' => $reserva->hora_fin,
                'ubicacion' => $reserva->ubicacion->nombre,
                'mesas' => $reserva->mesas->pluck('numero'),
                'cantidad_personas' => $reserva->cantidad_personas,
            ],
        ], 201); //201 codigo de estado http, que la peticion fue exitosa y se creo un registro
    }

    //mostrar en la vista mesas libres y ocupadas
    public function disponibilidad(Request $request, \App\Services\DisponibilidadService $disponibilidadService): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
        ]);

        $fechaHoraInicio = \Carbon\Carbon::parse($validated['fecha'].' '.$validated['hora_inicio']);
        $horaInicio = $fechaHoraInicio->format('H:i:s');
        $horaFin = (new \App\Services\HorarioValidator())
            ->calcularHoraFin($fechaHoraInicio)
            ->format('H:i:s');

        $resultado = \App\Models\Ubicacion::enOrdenDePrioridad()->map(function ($ubicacion) use ($disponibilidadService, $validated, $horaInicio, $horaFin) {
            $todasLasMesas = \App\Models\Mesa::where('ubicacion_id', $ubicacion->id)->get(['id', 'numero', 'capacidad']);
            $mesasLibresIds = $disponibilidadService
                ->mesasLibres($ubicacion->id, $validated['fecha'], $horaInicio, $horaFin)
                ->pluck('id');

            return [
                'ubicacion' => $ubicacion->nombre,
                'mesas' => $todasLasMesas->map(fn ($mesa) => [
                    'numero' => $mesa->numero,
                    'capacidad' => $mesa->capacidad,
                    'libre' => $mesasLibresIds->contains($mesa->id),//si el id de mesas libres coincide con el id de las mesas, devuelve true
                ]),
            ];
        });

        return response()->json(['fecha' => $validated['fecha'], 'hora_inicio' => $validated['hora_inicio'], 'ubicaciones' => $resultado]);
    }


    //devuelve a la vista la lista de reservas por una fecha, con una sola consulta SQL en lugar de varias consultas.
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        //seleciona los datos de la reserva,  obtiene informacion de ubicacion, devuelve el numero de mesas de la reserva (concat)
        //cuenta la cantidad de mesas de la reserva (count), a la tabla reservas le da el valor r (from), 
        //relaciona las tablas con inner join, filtra por fecha (where) y estado (and)
        //agrupa por reserva (group by) y ordena por ordend e ubicacion (order by)
        $reservas = DB::select("
            SELECT
                r.id,
                r.fecha,
                r.hora_inicio,
                r.hora_fin,
                r.cantidad_personas,
                r.cliente_nombre,
                r.cliente_telefono,
                u.id   AS ubicacion_id,
                u.nombre AS ubicacion_nombre,
                GROUP_CONCAT(m.numero ORDER BY m.numero SEPARATOR ', ') AS mesas,
                COUNT(m.id) AS cantidad_mesas
            FROM reservas r
            INNER JOIN ubicacions u ON u.id = r.ubicacion_id
            INNER JOIN reserva_mesa rm ON rm.reserva_id = r.id
            INNER JOIN mesas m ON m.id = rm.mesa_id
            WHERE r.fecha = ?
            AND r.estado = 'confirmada'
            GROUP BY r.id, r.fecha, r.hora_inicio, r.hora_fin, r.cantidad_personas,
                    r.cliente_nombre, r.cliente_telefono, u.id, u.nombre
            ORDER BY u.nombre ASC, r.hora_inicio ASC
        ", [$validated['fecha']]);


        //con collect convertimos el array de select a una coleccion, para agrupar por ubicacion_nombre y mandar a la vista
        $agrupadoPorUbicacion = collect($reservas)->groupBy('ubicacion_nombre');

        return response()->json([
            'fecha' => $validated['fecha'],
            'reservas_por_ubicacion' => $agrupadoPorUbicacion,
        ]);
    }
}
