@extends('reporting::layouts.master')

@section('title', 'Directorio de Fichas Unificadas')

@section('content')
    <div style="margin-bottom: 20px;">
        <h2 style="color: #0056b3; font-size: 14px; text-transform: uppercase; margin-bottom: 2px;">Directorio de Fichas
            Unificadas (MDM)</h2>
        <p style="font-size: 11px; color: #666; margin-top: 0;">Reporte administrativo de control de identidades, roles y
            accesos al sistema.</p>
    </div>

    <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
        <thead style="background-color: #f4f7f6; color: #333;">
            <tr>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Ficha</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Usuario</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Nombres y Apellidos</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Correo Electrónico</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Rol Asignado</th>
                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Estatus</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
                <tr>
                    <td style="padding: 8px; border: 1px solid #ddd; text-transform: uppercase;">
                        {{ substr($user->id, 0, 8) }}
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">
                        {{ $user->name }}
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd;">
                        {{-- Validamos de forma segura la existencia de la relación --}}
                        @if ($user->person)
                            {{ $user->person->first_name }} {{ $user->person->last_name }}
                        @else
                            <span style="color: #999;">Sin perfil personal</span>
                        @endif
                    </td>
                    <td style="padding: 8px; border: 1px solid #ddd;">{{ $user->email }}</td>

                    <td style="padding: 8px; border: 1px solid #ddd; text-align: center;">
                        @if (is_array($user->roles) && count($user->roles) > 0)
                            @php
                                // Traducimos cada rol usando el diccionario. Si no existe en el mapa, deja el valor original.
                                $mappedRoles = array_map(function ($role) use ($roleMap) {
                                    return $roleMap[$role] ?? $role;
                                }, $user->roles);
                            @endphp
                            {{ implode(', ', $mappedRoles) }}
                        @else
                            <span style="color: #999;">Sin Rol</span>
                        @endif
                    </td>

                    <td
                        style="padding: 8px; border: 1px solid #ddd; text-align: center; color: {{ !$user->trashed() ? '#28a745' : '#dc3545' }}">
                        {{ !$user->trashed() ? 'Activo' : 'Inactivo' }}
                    </td>
                </tr>
            @empty
                <!-- ... (Mismo bloque empty anterior, ajustando el colspan a 6) ... -->
                <tr>
                    <td colspan="6" style="padding: 15px; text-align: center; color: #888; border: 1px solid #ddd;">
                        No se encontraron registros de personal en el sistema.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 15px; font-size: 10px; color: #555; text-align: right;">
        <strong>Total de identidades registradas:</strong> {{ count($users) }}
    </div>
@endsection
