<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class RequirementService
{
    /**
     * Ejecuta la lógica de negocio para registrar un Requerimiento (Momento 1)
     * * @param array $validatedData Datos limpios del Request
     * @param UploadedFile $itRequestDoc Archivo PDF de Solicitud TI
     * @param UploadedFile $needsSpreadsheet Archivo de Matriz de Necesidades
     * @return array Datos del requerimiento creado
     */
    public function createRequirement(array $validatedData, UploadedFile $itRequestDoc, UploadedFile $needsSpreadsheet): array
    {
        // Envolvemos todo en una transacción ACID para garantizar la integridad
        return DB::transaction(function () use ($validatedData, $itRequestDoc, $needsSpreadsheet) {
            
            // 1. Almacenamiento seguro de archivos binarios
            $itDocPath = $itRequestDoc->store('requirements/it_docs');
            $needsDocPath = $needsSpreadsheet->store('requirements/needs_docs');

           // 2. Captura del Grafo Organizacional y el ID Real del Consultor
            $snapshot = DB::table('security.functional_consultants as fc')
                ->join('catalogs.requesting_units as ru', 'fc.requesting_unit_id', '=', 'ru.id')
                ->join('catalogs.systems as sys', 'ru.system_id', '=', 'sys.id')
                ->join('catalogs.societies as soc', 'sys.society_id', '=', 'soc.id')
                // Buscamos por el person_id que nos envió el frontend
                ->where('fc.person_id', $validatedData['functional_consultant_id'])
                ->select(
                    'fc.id as real_consultant_id',
                    'ru.name as unit_name', 
                    'sys.name as system_name', 
                    'soc.name as society_name'
                )
                ->first();

            $requirementId = Str::uuid()->toString();

            // 3. Persistencia del Requerimiento Principal
            DB::table('core.requirements')->insert([
                'id' => $requirementId,
                'rrti' => $validatedData['rrti'],
                'requirement_type' => $validatedData['requirement_type'],
                'management_type' => $validatedData['management_type'],
                'creation_date' => $validatedData['creation_date'],
                'description' => $validatedData['description'],
                'status' => 'PL', 
                'is_locked' => false,
                
                // === Usamos el ID real que acabamos de extraer ===
                'functional_consultant_id' => $snapshot->real_consultant_id,
                
                'it_request_doc_path' => $itDocPath,
                'needs_spreadsheet_path' => $needsDocPath,
                
                'snapshot_society_name' => $snapshot->society_name,
                'snapshot_system_name' => $snapshot->system_name,
                'snapshot_unit_name' => $snapshot->unit_name,
                
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Mapeo e inserción en la tabla pivote de Consultores CSPE
            $cspePivotData = collect($validatedData['cspe_consultants'])->map(function ($cspeId) use ($requirementId) {
                return [
                    'id' => Str::uuid()->toString(),
                    'requirement_id' => $requirementId,
                    'cspe_consultant_id' => $cspeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            DB::table('core.cspe_consultant_requirement')->insert($cspePivotData);

            // Retornamos el resultado al controlador
            return [
                'id' => $requirementId,
                'rrti' => $validatedData['rrti']
            ];
        });
    }
}