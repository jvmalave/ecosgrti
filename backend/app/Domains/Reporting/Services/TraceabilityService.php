<?php

namespace App\Domains\Reporting\Services;

use Illuminate\Support\Facades\DB;

class TraceabilityService
{
    public function getComponentTimeline(string $term): array
    {
        $results = [];

        // 1. BUSCAR EN ROLES (workflow.requirements_roles)
        $roles = DB::table('workflow.requirements_roles as r_role')
            ->join('core.requirements as req', 'r_role.requirement_id', '=', 'req.id')
            // Ruta exacta según esquema: Requirements -> Functional_Consultants -> Persons
            ->leftJoin('security.functional_consultants as fc', 'req.functional_consultant_id', '=', 'fc.id')
            ->leftJoin('security.persons as p_func', 'fc.person_id', '=', 'p_func.id')
            ->where('r_role.role_name', 'ILIKE', "%{$term}%")
            ->select(
                'r_role.id as component_id',
                'r_role.role_name as component_name',
                'req.id as req_id',
                'req.rrti',
                'req.status as req_status',
                'req.requirement_type',
                DB::raw("COALESCE(p_func.first_name || ' ' || p_func.last_name, 'Sin asignar') as functional_consultant")
            )->get();

        foreach ($roles as $role) {
            $results[] = [
                'rrti'                  => $role->rrti,
                'requirement_type'      => $role->requirement_type,
                'req_status'            => $role->req_status,
                'functional_consultant' => $role->functional_consultant,
                'component_name'        => $role->component_name,
                'component_type'        => 'ROLE',
                'timeline'              => $this->buildRoleTimeline($role->component_id)
            ];
        }

        // 2. BUSCAR EN ENTREGABLES (workflow.deliverables)
        $deliverables = DB::table('workflow.deliverables as deliv')
            ->join('core.requirements as req', 'deliv.requirement_id', '=', 'req.id')
            // Ruta exacta según esquema: Requirements -> Functional_Consultants -> Persons
            ->leftJoin('security.functional_consultants as fc', 'req.functional_consultant_id', '=', 'fc.id')
            ->leftJoin('security.persons as p_func', 'fc.person_id', '=', 'p_func.id')
            ->where('deliv.name', 'ILIKE', "%{$term}%")
            ->select(
                'deliv.id as component_id',
                'deliv.name as component_name',
                'req.id as req_id',
                'req.rrti',
                'req.status as req_status',
                'req.requirement_type',
                DB::raw("COALESCE(p_func.first_name || ' ' || p_func.last_name, 'Sin asignar') as functional_consultant")
            )->get();

        foreach ($deliverables as $deliv) {
            $results[] = [
                'rrti'                  => $deliv->rrti,
                'requirement_type'      => $deliv->requirement_type,
                'req_status'            => $deliv->req_status,
                'functional_consultant' => $deliv->functional_consultant ?? 'Sin asignar',
                'component_name'        => $deliv->component_name,
                'component_type'        => 'DELIVERABLE',
                'timeline'              => $this->buildDeliverableTimeline($deliv->component_id)
            ];
        }

        return $results;
    }

  /**
     * Construye la línea de tiempo cronológica para un Rol
     */
    private function buildRoleTimeline(string $roleId): array
    {
        $timeline = [];


        // 1. FASE: Diseño Técnico (DT)
        $dtRegisters = DB::table('workflow.dt_registers as dtr')
            ->join('workflow.dt_roles as dtrole', 'dtr.role_id', '=', 'dtrole.id')
            
            // 1er LEFT JOIN: Para extraer al CREADOR
            ->leftJoin('security.users as cu', 'dtr.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            
            // 2do LEFT JOIN: Para extraer al ACTUALIZADOR
            ->leftJoin('security.users as uu', 'dtr.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            
            ->where('dtrole.requirement_role_id', $roleId)
            
            ->select(
                'dtr.created_at as date',
                'dtr.updated_at',
                'dtr.title as action',
                'dtr.description as details',
                // Nombres compuestos con fallback
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('dtr.created_at', 'asc')
            ->get();

        foreach ($dtRegisters as $reg) {
            // Lógica inteligente para armar el texto del actor
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            // Evaluamos si el registro fue modificado después de su creación original
            if ($reg->updated_at && $reg->updated_at !== $reg->date) {
                if ($creator === $updater) {
                    $reg->actor = "Creado y Actualizado por:  {$creator} ";
                } else {
                    $reg->actor = "Creado por:  {$creator}  | Actualizado por:  {$updater}";
                }
            } else {
                // Si nunca se ha editado, solo mostramos al creador
                $reg->actor = " Creado por:  {$creator}";
            }

            $timeline[] = $this->formatEvent('Diseño Técnico (DT)', $reg, 'fa-laptop-code', 'text-primary');
        }

        // 2. FASE: Construcción (COR)
        $corRegisters = DB::table('workflow.cor_roles as cor')
            ->join('workflow.cor_registers as corr', 'cor.id', '=', 'corr.role_id')
            ->leftJoin('security.users as cu', 'corr.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'corr.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('cor.requirement_role_id', $roleId)
            ->select(
                'corr.created_at as date', 
                'corr.updated_at',
                'corr.title as action', 
                'corr.description as details', 
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('corr.created_at', 'asc')->get();

        foreach ($corRegisters as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            if ($reg->updated_at && $reg->updated_at !== $reg->date) {
                $reg->actor = ($creator === $updater) 
                    ? "Creado y Actualizado por: {$creator}" 
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Construcción (COR)', $reg, 'fa-cogs', 'text-brand');
        }

        // 3. FASE: Pruebas Integrales (PI) - Bitácora de Pruebas
        $piRegisters = DB::table('workflow.pi_roles as pi')
            ->join('workflow.pi_registers as pir', 'pi.id', '=', 'pir.pi_role_id')
            ->leftJoin('security.users as cu', 'pir.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'pir.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('pi.requirement_role_id', $roleId)
            ->select(
                'pir.created_at as date', 
                'pir.updated_at',
                'pir.title as action', 
                'pir.description as details',
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('pir.created_at', 'asc')->get();

        foreach ($piRegisters as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            if ($reg->updated_at && $reg->updated_at !== $reg->date) {
                $reg->actor = ($creator === $updater) 
                    ? "Creado y Actualizado por: {$creator}" 
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Pruebas Integrales (PI)', $reg, 'fa-bug', 'text-warning');
        }

        // 3.1 FASE: Pruebas Integrales (PI) - Aprobación Funcional
        $piApprovals = DB::table('workflow.pi_roles as pi')
            ->join('workflow.pi_functional_approvals as pia', 'pi.id', '=', 'pia.pi_role_id')
            ->leftJoin('security.users as cu', 'pia.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'pia.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('pi.requirement_role_id', $roleId)
            ->select(
                'pia.created_at as date', 
                'pia.updated_at',
                DB::raw("'Aprobación Funcional' as action"), 
                DB::raw("'Aprobado por: ' || pia.approver_name as details"), 
                'pia.file_path as support_file',
                'pia.original_name as support_filename', 
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('pia.created_at', 'asc')->get();

        foreach ($piApprovals as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            if ($reg->updated_at && $reg->updated_at !== $reg->date) {
                $reg->actor = ($creator === $updater) 
                    ? "Creado y Actualizado por: {$creator}" 
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Aprobación Funcional (PI)', $reg, 'fa-file-signature', 'text-warning');
        }

        // 4. FASE: Certificación (CER)
        $cerRegisters = DB::table('workflow.cer_roles as cer')
            ->leftJoin('workflow.cer_tickets as cert', 'cer.ticket_id', '=', 'cert.id')
            ->leftJoin('security.users as cu', 'cer.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'cer.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('cer.requirement_role_id', $roleId)
            ->select(
                'cer.updated_at as date',
                'cer.created_at as original_date',
                'cert.result_file as support_file', 
                DB::raw("COALESCE('Ticket de Certificación: ' || cert.ticket_number, 'Evaluación CER') as action"),
                DB::raw("CASE 
                    WHEN cer.status = 'Rechazado' THEN 'Rechazado: ' || cer.rejection_reason 
                    WHEN cer.status = 'CERTIFIED' THEN 'CERTIFICADO'
                    ELSE cer.status 
                END as details"),
                DB::raw("'Resultado_Certificacion.pdf' as support_filename"),
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('cer.updated_at', 'asc')->get();

        foreach ($cerRegisters as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            if ($reg->original_date && $reg->date !== $reg->original_date) {
                $reg->actor = ($creator === $updater) 
                    ? "Creado y Actualizado por: {$creator}" 
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Certificación (CER)', $reg, 'fa-user-check', 'text-info');
        }

        // 5. FASE: Pase a Producción (PAP)
        $papRegisters = DB::table('workflow.pap_roles as pap')
            ->leftJoin('workflow.pap_orders as papo', 'pap.order_id', '=', 'papo.id')
            ->leftJoin('security.users as cu', 'pap.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'pap.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('pap.requirement_role_id', $roleId)
            ->select(
                'pap.updated_at as date',
                'pap.created_at as original_date',
                'papo.result_file as support_file',
                DB::raw("COALESCE('Orden de Transporte: ' || papo.order_number, 'Proceso PAP') as action"),
                DB::raw("'Resultado_Orden_Transporte.pdf' as support_filename"),
                DB::raw("CASE 
                    WHEN pap.status = 'IN_PRODUCTION' THEN 'EN PRODUCCIÓN'
                    WHEN pap.status = 'Rechazado' THEN 'Rechazado: ' || pap.fail_reason 
                    ELSE pap.status 
                END as details"),
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('pap.updated_at', 'asc')->get();

        foreach ($papRegisters as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            if ($reg->original_date && $reg->date !== $reg->original_date) {
                $reg->actor = ($creator === $updater) 
                    ? "Creado y Actualizado por: {$creator}" 
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Pase a Producción (PAP)', $reg, 'fa-rocket', 'text-success');
        }

        // 6. FASE: Asignación a Usuario (AU)
        $auRegisters = DB::table('workflow.au_roles as au')
            ->leftJoin('workflow.au_tickets as aut', 'au.ticket_id', '=', 'aut.id')
            ->leftJoin('security.users as cu', 'au.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'au.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('au.requirement_role_id', $roleId)
            ->select(
                'au.updated_at as date',
                'au.created_at as original_date',
                'aut.result_file as support_file',
                DB::raw("COALESCE('Ticket de Asignación: ' || aut.ticket_number, 'Cierre Administrativo') as action"),
                DB::raw("'Resultado_Asignación.pdf' as support_filename"),
                DB::raw("CASE 
                    WHEN au.status = 'ASSIGNED' THEN 'ASIGNADO'
                    WHEN au.status = 'Rechazado' THEN 'Rechazado: ' || au.rejection_reason 
                    ELSE au.status 
                END as details"),
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('au.updated_at', 'asc')->get();

        foreach ($auRegisters as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;
            
            if ($reg->original_date && $reg->date !== $reg->original_date) {
                $reg->actor = ($creator === $updater) 
                    ? "Creado y Actualizado por: {$creator}" 
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Asignación a Usuario (AU)', $reg, 'fa-user-tag', 'text-secondary');
        }

        // Ordenar el timeline final cronológicamente
        usort($timeline, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
        
      }

    /**
     * Construye la línea de tiempo cronológica para un Entregable
     */
    private function buildDeliverableTimeline(string $deliverableId): array
    {
        $timeline = [];

        // 1. FASE: Centro de Operaciones Especiales (COE)
        $coeActivities = DB::table('workflow.coe_deliverables as coe')
            ->join('workflow.coe_activities as coea', 'coe.id', '=', 'coea.coe_deliverable_id')
            ->leftJoin('security.users as cu', 'coea.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'coea.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('coe.deliverable_id', $deliverableId)
            ->whereNull('coea.deleted_at')
            ->select(
                'coea.created_at as date', 
                'coea.updated_at',
                'coea.title as action', 
                'coea.description as details', 
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('coea.created_at', 'asc')->get();

        foreach ($coeActivities as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;

            if ($reg->updated_at && $reg->updated_at !== $reg->date) {
                $reg->actor = ($creator === $updater)
                    ? "Creado y Actualizado por: {$creator}"
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Ejecución COE', $reg, 'fa-file-alt', 'text-primary');
        }

        // 2. FASE: Certificación de Entregable Especial (CEE)
        $ceeActivities = DB::table('workflow.cee_deliverables as cee')
            ->leftJoin('workflow.cee_tickets as ceet', 'cee.ticket_id', '=', 'ceet.id')
            ->leftJoin('security.users as cu', 'cee.created_by', '=', 'cu.id')
            ->leftJoin('security.persons as cp', 'cu.email', '=', 'cp.email')
            ->leftJoin('security.users as uu', 'cee.updated_by', '=', 'uu.id')
            ->leftJoin('security.persons as up', 'uu.email', '=', 'up.email')
            ->where('cee.deliverable_id', $deliverableId)
            ->select(
                'cee.updated_at as date',
                'cee.created_at as original_date',
                'ceet.file_path as support_file',
                DB::raw("COALESCE('Ticket de Cert. Entregable: ' || ceet.ticket_number, 'Evaluación CEE') as action"),
                DB::raw("CASE 
                    WHEN cee.status = 'Rechazado' THEN 'Rechazado: ' || cee.rejection_reason 
                    WHEN cee.status = 'CERTIFIED' THEN 'CERTIFICADO'
                    ELSE cee.status 
                END as details"),
                DB::raw("COALESCE(cp.first_name || ' ' || cp.last_name, cu.name, 'Consultor CSPE') as creator_name"),
                DB::raw("COALESCE(up.first_name || ' ' || up.last_name, uu.name, 'Consultor CSPE') as updater_name")
            )
            ->orderBy('cee.updated_at', 'asc')->get();

        foreach ($ceeActivities as $reg) {
            $creator = $reg->creator_name;
            $updater = $reg->updater_name;

            if ($reg->original_date && $reg->date !== $reg->original_date) {
                $reg->actor = ($creator === $updater)
                    ? "Creado y Actualizado por: {$creator}"
                    : "Creado por: {$creator} | Actualizado por: {$updater}";
            } else {
                $reg->actor = "Creado por: {$creator}";
            }

            $timeline[] = $this->formatEvent('Certificación (CEE)', $reg, 'fa-check-double', 'text-success');
        }

        usort($timeline, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }
    /**
     * Estandariza la salida para que Angular la pinte fácilmente
     */
    private function formatEvent(string $phaseName, object $data, string $icon, string $colorClass): array
    {
        return [
            'phase'            => $phaseName,
            'date'             => $data->date,
            'actor'            => $data->actor ?? 'Sistema',
            'action'           => $data->action,
            'details'          => $data->details,
            'icon'             => $icon,
            'color'            => $colorClass,
            'support_file'     => $data->support_file ?? null,
            'support_filename' => $data->support_filename ?? null 
        ];
    }

    

}