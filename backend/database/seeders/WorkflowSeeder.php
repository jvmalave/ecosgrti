<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domains\Core\Models\Requirement;
use App\Domains\Workflow\Models\RequirementRole;
use App\Domains\Workflow\Models\Deliverable;
use App\Domains\Workflow\Models\AtfAgreement;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Extraemos de forma segura los IDs que ya fueron creados por los Seeders anteriores
        $consultantId = DB::table('functional_consultants')->value('id');
        $userId = DB::table('users')->value('id');

        if (!$consultantId || !$userId) {
            $this->command->error('Faltan datos base. Asegúrate de que UserSeeder y TestConsultantsSeeder se ejecuten antes.');
            return;
        }

        // ---------------------------------------------------------
        // ESCENARIO 1: Requerimiento Recién Iniciado (0%)
        // ---------------------------------------------------------
        Requirement::factory()->create([
            'description' => 'Sistema de Gestión de Inventario (Fase Inicial)',
            'management_type' => 'Mixto',
            'status' => 'ATF_OPEN',
            'progress_percentage' => 0,
            'is_locked' => false,
            'functional_consultant_id' => $consultantId
        ]);

        // ---------------------------------------------------------
        // ESCENARIO 2: Requerimiento en Progreso (Parcial)
        // ---------------------------------------------------------
        $reqInProgress = Requirement::factory()->create([
            'description' => 'Módulo de Facturación Electrónica (En Progreso)',
            'management_type' => 'Mixto',
            'status' => 'ATF_OPEN',
            'progress_percentage' => 66,
            'is_locked' => false,
            'functional_consultant_id' => $consultantId
        ]);

        RequirementRole::factory()->count(2)->create(['requirement_id' => $reqInProgress->id]);
        Deliverable::factory()->count(1)->create(['requirement_id' => $reqInProgress->id]);

        // ---------------------------------------------------------
        // ESCENARIO 3: Requerimiento Cerrado (Hard Gate Aplicado)
        // ---------------------------------------------------------
        $reqLocked = Requirement::factory()->create([
            'description' => 'Migración de Base de Datos Core (Cerrado e Inmutable)',
            'management_type' => 'Mixto',
            'status' => 'CLOSED',
            'progress_percentage' => 100,
            'is_locked' => true,
            'functional_consultant_id' => $consultantId
        ]);

        // Para el acuerdo ATF, usamos el userId ya que el campo es registered_by_user_id
        AtfAgreement::factory()->create([
            'requirement_id' => $reqLocked->id,
            'registered_by_user_id' => $userId,
            'description' => 'Aprobado por el comité técnico en sesión extraordinaria.'
        ]);
        RequirementRole::factory()->create(['requirement_id' => $reqLocked->id]);
        Deliverable::factory()->create(['requirement_id' => $reqLocked->id]);
    }
}