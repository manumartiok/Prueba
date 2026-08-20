<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No hay login en el alcance de esta prueba, se autoriza siempre
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'cantidad_personas' => ['required', 'integer', 'min:1', 'max:24'], // 24 = max(3 mesas de 8)
            'cliente_nombre' => ['nullable', 'string', 'max:100'],
            'cliente_telefono' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.after_or_equal' => 'No se pueden hacer reservas para fechas pasadas.',
            'cantidad_personas.max' => 'La cantidad de personas supera la capacidad maxima combinable (3 mesas).',
        ];
    }
}
