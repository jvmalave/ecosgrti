<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ==========================================
        // 1. ESQUEMA CORE
        // ==========================================
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN rrti TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN requirement_type TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN management_type TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN needs_spreadsheet_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN it_request_doc_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN snapshot_society_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN snapshot_system_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN snapshot_unit_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN status TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN closure_act_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN notification_support_path TYPE VARCHAR;');

        DB::statement('ALTER TABLE core.estimated_phases ALTER COLUMN phase_name TYPE VARCHAR;');

        // ==========================================
        // 2. ESQUEMA CATALOGS
        // ==========================================
        DB::statement('ALTER TABLE catalogs.societies ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.societies ALTER COLUMN acronym TYPE VARCHAR;');

        DB::statement('ALTER TABLE catalogs.systems ALTER COLUMN name TYPE VARCHAR;');

        DB::statement('ALTER TABLE catalogs.requesting_units ALTER COLUMN name TYPE VARCHAR;');

        DB::statement('ALTER TABLE catalogs.requirement_types ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.requirement_types ALTER COLUMN description TYPE VARCHAR;');

        DB::statement('ALTER TABLE catalogs.management_types ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.management_types ALTER COLUMN description TYPE VARCHAR;');

        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN management_type TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN phase TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN phase_code TYPE VARCHAR;');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN status_code TYPE VARCHAR;');

        // ==========================================
        // 3. ESQUEMA SECURITY
        // ==========================================
        DB::statement('ALTER TABLE security.persons ALTER COLUMN first_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE security.persons ALTER COLUMN last_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE security.persons ALTER COLUMN email TYPE VARCHAR;');
        DB::statement('ALTER TABLE security.persons ALTER COLUMN phone TYPE VARCHAR;');

        DB::statement('ALTER TABLE security.special_credentials ALTER COLUMN pin_hash TYPE VARCHAR;');

        DB::statement('ALTER TABLE security.users ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE security.users ALTER COLUMN email TYPE VARCHAR;');
        DB::statement('ALTER TABLE security.users ALTER COLUMN password TYPE VARCHAR;');
        DB::statement('ALTER TABLE security.users ALTER COLUMN remember_token TYPE VARCHAR;');

        // ==========================================
        // 4. ESQUEMA AUDIT
        // ==========================================
        DB::statement('ALTER TABLE audit.audit_logs ALTER COLUMN action TYPE VARCHAR;');

        // ==========================================
        // 5. ESQUEMA WORKFLOW
        // ==========================================
        DB::statement('ALTER TABLE workflow.au_roles ALTER COLUMN status TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.au_roles ALTER COLUMN planilla_path TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN ticket_number TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN file_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN status TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN result_category TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN result_file TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.cee_deliverables ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN ticket_number TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN file_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN status TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN result_category TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN result_file TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.cer_roles ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN ticket_number TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN file_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN status TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN result_category TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN result_file TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.coe_activities ALTER COLUMN title TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.coe_deliverables ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.cor_registers ALTER COLUMN title TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.cor_roles ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.cor_roles ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.deliverables ALTER COLUMN name TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.dt_registers ALTER COLUMN title TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.dt_roles ALTER COLUMN name TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.dt_roles ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN order_number TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN file_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN status TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN result_category TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN result_file TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.pap_roles ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.pi_functional_approvals ALTER COLUMN approver_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.pi_functional_approvals ALTER COLUMN file_path TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.pi_functional_approvals ALTER COLUMN original_name TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.pi_registers ALTER COLUMN title TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.pi_roles ALTER COLUMN status TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.pi_test_users ALTER COLUMN identifier TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.requirement_phase_history ALTER COLUMN phase_status_code TYPE VARCHAR;');

        DB::statement('ALTER TABLE workflow.requirements_roles ALTER COLUMN role_name TYPE VARCHAR;');
        DB::statement('ALTER TABLE workflow.requirements_roles ALTER COLUMN assignment_type TYPE VARCHAR;');
    }

    public function down(): void
    {
        // ==========================================
        // REVERSIÓN (Devolver a los tamaños originales)
        // ==========================================
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN rrti TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN requirement_type TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN management_type TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN needs_spreadsheet_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN it_request_doc_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN snapshot_society_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN snapshot_system_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN snapshot_unit_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN status TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN closure_act_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE core.requirements ALTER COLUMN notification_support_path TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE core.estimated_phases ALTER COLUMN phase_name TYPE VARCHAR(50);');

        DB::statement('ALTER TABLE catalogs.societies ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE catalogs.societies ALTER COLUMN acronym TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE catalogs.systems ALTER COLUMN name TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE catalogs.requesting_units ALTER COLUMN name TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE catalogs.requirement_types ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE catalogs.requirement_types ALTER COLUMN description TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE catalogs.management_types ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE catalogs.management_types ALTER COLUMN description TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN management_type TYPE VARCHAR(20);');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN phase TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN phase_code TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE catalogs.milestones ALTER COLUMN status_code TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE security.persons ALTER COLUMN first_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE security.persons ALTER COLUMN last_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE security.persons ALTER COLUMN email TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE security.persons ALTER COLUMN phone TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE security.special_credentials ALTER COLUMN pin_hash TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE security.users ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE security.users ALTER COLUMN email TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE security.users ALTER COLUMN password TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE security.users ALTER COLUMN remember_token TYPE VARCHAR(100);');

        DB::statement('ALTER TABLE audit.audit_logs ALTER COLUMN action TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.au_roles ALTER COLUMN status TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.au_roles ALTER COLUMN planilla_path TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN ticket_number TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN file_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN status TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN result_category TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.au_tickets ALTER COLUMN result_file TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.cee_deliverables ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN ticket_number TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN file_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN status TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN result_category TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cee_tickets ALTER COLUMN result_file TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.cer_roles ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN ticket_number TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN file_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN status TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN result_category TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cer_tickets ALTER COLUMN result_file TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.coe_activities ALTER COLUMN title TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.coe_deliverables ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.cor_registers ALTER COLUMN title TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.cor_roles ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.cor_roles ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.deliverables ALTER COLUMN name TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.dt_registers ALTER COLUMN title TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.dt_roles ALTER COLUMN name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.dt_roles ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN order_number TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN file_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN status TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN result_category TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.pap_orders ALTER COLUMN result_file TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.pap_roles ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.pi_functional_approvals ALTER COLUMN approver_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.pi_functional_approvals ALTER COLUMN file_path TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.pi_functional_approvals ALTER COLUMN original_name TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.pi_registers ALTER COLUMN title TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.pi_roles ALTER COLUMN status TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.pi_test_users ALTER COLUMN identifier TYPE VARCHAR(255);');

        DB::statement('ALTER TABLE workflow.requirement_phase_history ALTER COLUMN phase_status_code TYPE VARCHAR(30);');

        DB::statement('ALTER TABLE workflow.requirements_roles ALTER COLUMN role_name TYPE VARCHAR(255);');
        DB::statement('ALTER TABLE workflow.requirements_roles ALTER COLUMN assignment_type TYPE VARCHAR(255);');
    }
};