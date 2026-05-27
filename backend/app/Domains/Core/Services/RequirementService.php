<?php

namespace App\Domains\Core\Services;

use App\Domains\Core\Models\Requirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class RequirementService
{
    /**
     * Crea un nuevo requerimiento, sube sus archivos y asigna los consultores (Momento 1)
     */
    public function createRequirement(array $data, UploadedFile $needsFile, UploadedFile $itDocFile)
    {
        // DB::transaction garantiza que si algo falla, los cambios se reviertan (Rollback)
        return DB::transaction(function () use ($data, $needsFile, $itDocFile) {
            
        // 1. Guardar archivos
            $needsPath = $needsFile->store('requirements/needs', 'public');
            $itDocPath = $itDocFile->store('requirements/it_docs', 'public');

            // 2. Crear el requerimiento de forma normal (Tu Trait HasUuid hará su magia aquí)
            $requirement = Requirement::create([
                'rrti' => $data['rrti'],
                'requirement_type' => $data['requirement_type'],
                'creation_date' => $data['creation_date'],
                'description' => $data['description'],
                'management_type' => $data['management_type'],
                'functional_consultant_id' => $data['functional_consultant_id'],
                'needs_spreadsheet_path' => $needsPath,
                'it_request_doc_path' => $itDocPath,
                'status' => 'PL',
                'is_locked' => false,
            ]);

            // 3. Traemos el ID 100% real y puro directo desde PostgreSQL usando DB::table
            // Esto salta cualquier bug de Traits o Modelos en memoria RAM.
            $dbRequirement = DB::table('core.requirements')->where('rrti', $data['rrti'])->first();

            // 4. EL MARTILLO: Armamos el Query manualmente
            $pivotData = [];
            foreach ($data['cspe_consultants'] as $consultantId) {
                $pivotData[] = [
                    'id'                 => Str::uuid()->toString(),
                    'requirement_id'     => $dbRequirement->id, // Usamos el ID puro de la BD
                    'cspe_consultant_id' => $consultantId,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ];
            }
            
            // 5. Inserción cruda. Sin attach(), sin magia, 100% control nuestro.
            DB::table('core.cspe_consultant_requirement')->insert($pivotData);

            // Devolvemos el requerimiento original para tu respuesta JSON
            return $requirement;
            
        });
    }
}