<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservaRequest;
use App\Services\ReservaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

class ReservaController extends Controller
{
    public function __construct(private ReservaService $reservaService)
    {
    }

    /**
     * PUNTO 3: crea una reserva. Recibe fecha, hora y cantidad de personas;
     * el sistema resuelve ubicacion y mesas automaticamente.
     */
    public function store(ReservaRequest $request): JsonResponse
    {
        try {
            $reserva = $this->reservaService->crear($request->validated());
        } catch (InvalidArgumentException $e) {
            // errores de horario invalido / anticipacion insuficiente -> 422
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            // sin disponibilidad en ninguna ubicacion -> 409 Conflict
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
        ], 201);
    }

    /**
     * Endpoint auxiliar (no pedido explicitamente en la consigna, pero
     * necesario para el frontend): devuelve, para una fecha+hora dada,
     * todas las mesas de todas las ubicaciones con su estado libre/ocupada.
     * Usa el mismo DisponibilidadService (con su cache) que usa la creacion
     * de reservas, para que el frontend vea exactamente lo mismo que el
     * sistema usa internamente para decidir.
     */
    public function disponibilidad(Request $request, \App\Services\DisponibilidadService $disponibilidadService): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora_inicio' => ['required', 'date_format:H:i'],
        ]);

        $horaInicio = $validated['hora_inicio'].':00';
        $horaFin = \Carbon\Carbon::parse($horaInicio)
            ->addMinutes(\App\Services\HorarioValidator::DURACION_MINUTOS)
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
                    'libre' => $mesasLibresIds->contains($mesa->id),
                ]),
            ];
        });

        return response()->json(['fecha' => $validated['fecha'], 'hora_inicio' => $validated['hora_inicio'], 'ubicaciones' => $resultado]);
    }

    /**
     * PUNTO 4: listado de reservas de una fecha, agrupadas por ubicacion y
     * seccion, mostrando las mesas de cada una, en UNA SOLA consulta SQL
     * optimizada (evita el problema N+1 de traer las mesas reserva por
     * reserva).
     *
     * Se usa SQL crudo con JOIN + GROUP_CONCAT para traer, en una unica
     * fila por reserva, el listado de mesas involucradas ya concatenado.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        $reservas = DB::select("
            SELECT
                r.id,
                r.fecha,
                r.hora_inicio,
                r.hora_fin,
                r.cantidad_personas,
                r.cliente_nombre,
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
                     r.cliente_nombre, u.id, u.nombre
            ORDER BY u.nombre ASC, r.hora_inicio ASC
        ", [$validated['fecha']]);

        // Se agrupa por ubicacion en PHP (barato, ya son pocas filas) para
        // devolver una respuesta mas comoda de consumir por el frontend.
        $agrupadoPorUbicacion = collect($reservas)->groupBy('ubicacion_nombre');

        return response()->json([
            'fecha' => $validated['fecha'],
            'reservas_por_ubicacion' => $agrupadoPorUbicacion,
        ]);
    }
}
