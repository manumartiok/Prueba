<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class HorarioValidator
{
    /**
     * Duracion fija de cada reserva, segun consigna.
     */
    public const DURACION_MINUTOS = 120;

    /**
     * Minutos minimos de anticipacion para poder reservar.
     */
    public const ANTICIPACION_MINUTOS = 15;

    /**
     * Rangos de atencion por dia de la semana, en minutos desde las 00:00.
     * El sabado cierra a las 2AM del domingo, por eso el fin (26*60=1560)
     * supera los 1440 minutos de un dia normal: se representa como
     * "sabado + 2 horas del dia siguiente" para simplificar los calculos.
     *
     * Carbon::dayOfWeek: 0=domingo, 1=lunes ... 6=sabado
     */
    private const RANGOS = [
        0 => ['inicio' => 12 * 60, 'fin' => 16 * 60],       // domingo 12 a 16
        1 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // lunes 10 a 24
        2 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // martes
        3 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // miercoles
        4 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // jueves
        5 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // viernes
        6 => ['inicio' => 22 * 60, 'fin' => 26 * 60],       // sabado 22 a 2AM (+1 dia)
    ];

    /**
     * Valida que la fecha/hora solicitada este dentro del horario de atencion
     * (incluyendo que la reserva completa, con sus 2hs de duracion, entre
     * dentro del horario) y que respete los 15 minutos de anticipacion minima.
     *
     * SUPUESTO: se asume que la reserva debe terminar (no solo empezar)
     * dentro del horario de atencion, ya que no tendria sentido operativo
     * que el local siga atendiendo una mesa despues de cerrar.
     *
     * @throws InvalidArgumentException si el horario no es valido
     */
    public function validar(Carbon $fechaHoraInicio): void
    {
        $this->validarAnticipacion($fechaHoraInicio);
        $this->validarDentroDeHorarioDeAtencion($fechaHoraInicio);
    }

    private function validarAnticipacion(Carbon $fechaHoraInicio): void
    {
        $minutosDeDiferencia = ($fechaHoraInicio->timestamp - Carbon::now()->timestamp) / 60;

        if ($minutosDeDiferencia < self::ANTICIPACION_MINUTOS) {
            throw new InvalidArgumentException(
                'La reserva debe hacerse con al menos '.self::ANTICIPACION_MINUTOS.' minutos de anticipacion.'
            );
        }
    }

    private function validarDentroDeHorarioDeAtencion(Carbon $fechaHoraInicio): void
    {
        $diaSemana = $fechaHoraInicio->dayOfWeek;
        $rango = self::RANGOS[$diaSemana];

        $minutosInicio = $fechaHoraInicio->hour * 60 + $fechaHoraInicio->minute;

        // Solo se valida que el INICIO este dentro del horario de atencion.
        // El FIN se recorta automaticamente al cierre (ver calcularHoraFin),
        // en vez de rechazar la reserva si no entran las 2hs completas.
        if ($minutosInicio < $rango['inicio'] || $minutosInicio >= $rango['fin']) {
            throw new InvalidArgumentException(
                'El horario solicitado esta fuera del rango de atencion para ese dia.'
            );
        }
    }

    public function calcularHoraFin(Carbon $fechaHoraInicio): Carbon
{
    $diaSemana = $fechaHoraInicio->dayOfWeek;
    $rango = self::RANGOS[$diaSemana];

    $minutosInicio = $fechaHoraInicio->hour * 60 + $fechaHoraInicio->minute;
    $minutosFinNormal = $minutosInicio + self::DURACION_MINUTOS;
    $minutosFinRecortado = min($minutosFinNormal, $rango['fin']);

    $minutosASumar = $minutosFinRecortado - $minutosInicio;

    return $fechaHoraInicio->copy()->addMinutes($minutosASumar);
}
}
