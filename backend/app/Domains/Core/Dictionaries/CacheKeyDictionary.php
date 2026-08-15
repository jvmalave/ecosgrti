<?php

declare(strict_types=1);

namespace App\Domains\Core\Dictionaries;

/**
 * Diccionario Centralizado de Llaves de Caché de Redis.
 * Única fuente de verdad para la nomenclatura de invalidación reactiva.
 */
final class CacheKeyDictionary
{
    // =========================================================================
    // 1. DOMINIO CATALOGS / MDM
    // =========================================================================

    /**
     * Árbol jerárquico de la Estructura Organizacional.
     */
    public static function orgStructureTree(): string
    {
        return 'org_structure_tree_master';
    }

    /**
     * Listado de unidades activas en la estructura organizacional.
     */
    public static function activeUnits(): string
    {
        return 'org_structure_active_units';
    }

    /**
     * Matriz de progreso activa según el tipo de gestión.
     */
    public static function activeProgressMatrix(string $managementType): string
    {
        return "active_matrix_{$managementType}";
    }

    // =========================================================================
    // 2. DOMINIO SECURITY / IDENTIDAD
    // =========================================================================

    /**
     * Listado general de consultores funcionales.
     */
    public static function functionalConsultantsList(): string
    {
        return 'list_functional_consultants_v4';
    }

    /**
     * Grafo de datos estructurados de una persona específica.
     */
    public static function personGraph(string $personId): string
    {
        return "grafo_persona_{$personId}";
    }

    /**
     * Listado unificado de identidades del sistema.
     */
    public static function identitiesList(): string
    {
        return 'identities_list_cache';
    }

    /**
     * Control de intentos fallidos de autenticación (Rate Limiting).
     */
    public static function loginAttempts(string $email): string
    {
        return "attempts_{$email}";
    }

    /**
     * Ticket efímero de un solo uso para operaciones especiales (Step-Up Auth).
     */
    public static function specialOperationTicket(string $tokenOrId): string
    {
        return "special_ops_ticket_{$tokenOrId}";
    }

    // =========================================================================
    // 3. NÚCLEO Y PROGRESO GLOBAL (CORE)
    // =========================================================================

    /**
     * Detalles cacheados de un requerimiento específico (Última versión).
     */
    public static function requirementDetail(string $requirementId): string
    {
        return "req_detail_v2_{$requirementId}";
    }

    /**
     * Arreglo con todas las versiones históricas del detalle del requerimiento.
     */
    public static function allRequirementDetailKeys(string $requirementId): array
    {
        return [
            "req_detail_{$requirementId}",
            self::requirementDetail($requirementId)
        ];
    }

    /**
     * Llave maestra del progreso algorítmico del requerimiento.
     */
    public static function requirementProgress(string $requirementId): string
    {
        return "req_{$requirementId}_progress";
    }

    /**
     * Llave de inmutabilidad (Hard Gate Global) que sella una fase completa.
     */
    public static function phaseFrozenFlag(string $requirementId, string $phasePrefix): string
    {
        return "req_{$requirementId}_" . strtolower($phasePrefix) . "_congelado";
    }

    // =========================================================================
    // 4. TABLEROS DE CONTROL (DASHBOARDS Y REACTIVIDAD)
    // =========================================================================

    /**
     * Etiqueta (Tag) global para agrupar las cachés del Dashboard.
     * Uso: Cache::tags([CacheKeyDictionary::dashboardTag()])->flush();
     */
    public static function dashboardTag(): string
    {
        return 'dashboard';
    }

    /**
     * Llave de versionamiento global para forzar la reactividad del frontend.
     */
    public static function globalDashboardVersion(): string
    {
        return 'dashboard_version';
    }

    /**
     * Resumen general de datos para el Dashboard de Requerimientos.
     */
    public static function requirementDashboardSummary(string $requirementId): string
    {
        return "req_dashboard_summary_{$requirementId}";
    }

    /**
     * Datos consolidados para el Dashboard de Progreso.
     */
    public static function progressDashboardData(string $requirementId): string
    {
        return "req_progress_dashboard_{$requirementId}";
    }

    // =========================================================================
    // 5. FASE ATF (ANÁLISIS TÉCNICO FUNCIONAL)
    // =========================================================================

    /**
     * Caché principal de estado de la fase ATF.
     */
    public static function atfCache(string $requirementId): string
    {
        return "atf_cache_{$requirementId}";
    }

    /**
     * Listado de componentes iniciales creados en la fase ATF.
     */
    public static function atfComponents(string $requirementId): string
    {
        return "atf_components_{$requirementId}";
    }

    // =========================================================================
    // 6. ORQUESTACIÓN DE COMPONENTES MAESTROS (ROLES Y ENTREGABLES)
    // =========================================================================

    /**
     * Lista polimórfica de componentes de una fase para un requerimiento.
     */
    public static function phaseComponentsList(string $requirementId, string $phasePrefix): string
    {
        return "req_{$requirementId}_" . strtolower($phasePrefix) . "_components_list";
    }

    // =========================================================================
    // 7. BITÁCORAS Y REGISTROS TÉCNICOS (DT, COR, COE, PI)
    // =========================================================================

    /**
     * Caché de los registros/actividades asociados estrictamente a un componente.
     */
    public static function componentRegisters(string $componentId, string $phasePrefix): string
    {
        return strtolower($phasePrefix) . "_registers_cache_{$componentId}";
    }

    // =========================================================================
    // 8. CERTIFICACIÓN: TICKETS Y APROBACIONES FUNCIONALES (CER, CEE)
    // =========================================================================

    /**
     * Listado de tickets de certificación emitidos para un requerimiento.
     */
    public static function phaseTicketsList(string $requirementId, string $phasePrefix): string
    {
        return "req_{$requirementId}_" . strtolower($phasePrefix) . "_tickets_list";
    }

    // =========================================================================
    // 9. PRUEBAS INTEGRALES: USUARIOS Y CALIDAD (PI)
    // =========================================================================

    /**
     * Listado de usuarios de prueba asignados a un rol específico.
     */
    public static function testUsersList(string $roleId): string
    {
        return "pi_test_users_cache_{$roleId}";
    }
}