<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException; //para los mensajes de errores

class HorarioValidator
{

    //define la duracion de una reserva
    public const DURACION_MINUTOS = 120;

    //minimo de tiempo con el que realizar la reserva
    public const ANTICIPACION_MINUTOS = 15;

    //Rango horario de los dias, al usar Carbon ya esta definido la enumeracion por eldia
    private const RANGOS = [
        0 => ['inicio' => 12 * 60, 'fin' => 16 * 60],       // domingo 12 a 16
        1 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // lunes 10 a 24
        2 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // martes
        3 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // miercoles
        4 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // jueves
        5 => ['inicio' => 10 * 60, 'fin' => 24 * 60],       // viernes
        6 => ['inicio' => 22 * 60, 'fin' => 26 * 60],       // sabado 22 a 2AM (+1 dia)
    ];


    //recibe fecha y hora con Carbon para validar las funciones privadas y determinar si el horario de la reserva es valido
    public function validar(Carbon $fechaHoraInicio): void //con void no retorna ningun valor, simplemente continua si esta bien, o si esta mal lanza un error
    {
        $this->validarAnticipacion($fechaHoraInicio);
        $this->validarDentroDeHorarioDeAtencion($fechaHoraInicio);
    }

    //se encarga de que al reserva sea con mas de 15 minutos de anticipacion
    private function validarAnticipacion(Carbon $fechaHoraInicio): void
    {
        $minutosDeDiferencia = ($fechaHoraInicio->timestamp - Carbon::now()->timestamp) / 60; //calcula diferencia horaria entre la reserva y la hora actual

        if ($minutosDeDiferencia < self::ANTICIPACION_MINUTOS) {//devuelve el mensaje de errores si la diferencia es menor a la constante
            throw new InvalidArgumentException(
                'La reserva debe hacerse con al menos '.self::ANTICIPACION_MINUTOS.' minutos de anticipacion.'
            );
        }
    }
    //revisa que la reserva sea durante el rango permitido de horario 
    private function validarDentroDeHorarioDeAtencion(Carbon $fechaHoraInicio): void
    {
        $diaSemana = $fechaHoraInicio->dayOfWeek;
        $rango = self::RANGOS[$diaSemana]; //self para usar la constante dentro de la clase sin invocar a la clase

        $minutosInicio = $fechaHoraInicio->hour * 60 + $fechaHoraInicio->minute; //convertir horas a minutos

        if ($minutosInicio < $rango['inicio'] || $minutosInicio >= $rango['fin']) { //define si el horario de la reserva esta en el rango y si no devuelve el error
            throw new InvalidArgumentException(
                'El horario solicitado esta fuera del rango de atencion para ese dia.'
            );
        }
    }

    //calcula el final de la reserva
    public function calcularHoraFin(Carbon $fechaHoraInicio): Carbon
{
    $diaSemana = $fechaHoraInicio->dayOfWeek;
    $rango = self::RANGOS[$diaSemana];

    $minutosInicio = $fechaHoraInicio->hour * 60 + $fechaHoraInicio->minute;
    $minutosFinNormal = $minutosInicio + self::DURACION_MINUTOS;
    $minutosFinRecortado = min($minutosFinNormal, $rango['fin']);//si el rango horario no da para completar las 2 horas, devuelve la reserva con la hora de cierre

    $minutosASumar = $minutosFinRecortado - $minutosInicio;

    return $fechaHoraInicio->copy()->addMinutes($minutosASumar);//le suma al horario inicial los minutos de la reserva
}
}
