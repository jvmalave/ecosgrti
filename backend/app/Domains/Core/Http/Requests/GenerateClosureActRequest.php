<?php

declare(strict_types=1);

namespace App\Domains\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class GenerateClosureActRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Reglas de validación base (RN-FR-02 y RN-FR-03).
     */
    public function rules(): array
    {
        return [
            'notification_date' => [
                'required',
                'date',
                'before:now', // Notif < NOW
            ],
            'completion_date' => [
                'required',
                'date',
                'before_or_equal:now', // Fin <= NOW
                'after_or_equal:notification_date', // Fin >= Notif
            ],
            'notification_file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:5120', // Máximo 5MB
            ],
            'conformity_declaration' => [
                'required',
                'accepted', // Valida que sea true, 'on', 'yes', o 1
            ],
        ];
    }

    /**
     * Configura el validador con reglas condicionales complejas (After Hooks).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Extraemos el requerimiento directamente del Route Binding
            $requirement = $this->route('requirement'); 
            
            if ($requirement && $this->notification_date && $this->completion_date) {
                // Convertimos las fechas a Carbon
                $startDate = Carbon::parse($requirement->creation_date); 
                $notifDate = Carbon::parse($this->notification_date);
                $compDate = Carbon::parse($this->completion_date);
                
                // Ambas > Inicio
                if ($notifDate->lte($startDate)) {
                    $validator->errors()->add(
                        'notification_date', 
                        'La fecha de notificación debe ser estrictamente posterior al inicio del requerimiento.'
                    );
                }
                
                if ($compDate->lte($startDate)) {
                    $validator->errors()->add(
                        'completion_date', 
                        'La fecha de fin de atención debe ser estrictamente posterior al inicio del requerimiento.'
                    );
                }
            }
        });
    }

    /**
     * Mensajes personalizados para el frontend.
     */
    public function messages(): array
    {
        return [
            'notification_date.before' => 'La fecha de notificación no puede estar en el futuro.',
            'completion_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la notificación.',
            'notification_file.mimes' => 'El soporte debe ser un archivo PDF.',
            'notification_file.max' => 'El documento no debe exceder los 5MB.',
            'conformity_declaration.accepted' => 'Debe confirmar la declaración de conformidad para proceder.',
        ];
    }
}