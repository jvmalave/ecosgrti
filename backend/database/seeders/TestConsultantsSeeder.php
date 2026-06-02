<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TestConsultantsSeeder extends Seeder
{
    public function run()
    {
        // 1. Generamos los UUIDs en memoria (usamos toString() para evitar errores con el driver de Postgres)
        $socId = Str::uuid()->toString();
        $sysId = Str::uuid()->toString();
        $unitId = Str::uuid()->toString();
        
        $personFuncId = Str::uuid()->toString();
        $personCspeId = Str::uuid()->toString();
        
        $funcConsultantId = Str::uuid()->toString();
        $cspeConsultantId = Str::uuid()->toString();

        $now = now();

        // ========================================================================
        // 2. DOMINIO CATALOGS: Insertamos la jerarquía organizacional estricta
        // ========================================================================
        DB::table('catalogs.societies')->insert([
            'id' => $socId, 
            'name' => 'Sociedad de Prueba C.A.',
            'acronym' => 'SPCA', // Campo requerido según el nuevo diseño
            'created_at' => $now,
            'updated_at' => $now
        ]);

        DB::table('catalogs.systems')->insert([
            'id' => $sysId, 
            'society_id' => $socId, // Llave foránea hacia la sociedad
            'name' => 'Sistema ERP Core',
            'created_at' => $now,
            'updated_at' => $now
        ]);

        DB::table('catalogs.requesting_units')->insert([
            'id' => $unitId, 
            'system_id' => $sysId,  // Llave foránea hacia el sistema
            'name' => 'Dirección de Finanzas',
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // ========================================================================
        // 3. DOMINIO SECURITY: Insertamos las Personas
        // ========================================================================
        DB::table('security.persons')->insert([
            'id' => $personFuncId, 
            'first_name' => 'Carlos',
            'last_name' => 'Funcional',
            'email' => 'carlos.funcional@cantv.com.ve',
            'phone' => '0412-0000000',
            'created_at' => $now,
            'updated_at' => $now
        ]);

        DB::table('security.persons')->insert([
            'id' => $personCspeId, 
            'first_name' => 'Ana',
            'last_name' => 'CSPE',
            'email' => 'ana.cspe@cantv.com.ve',
            'phone' => '0416-0000000',
            'created_at' => $now,
            'updated_at' => $now
        ]);

        // 4. Insertamos los roles de Consultores
        DB::table('security.functional_consultants')->insert([
            'id' => $funcConsultantId,
            'person_id' => $personFuncId,
            'requesting_unit_id' => $unitId,
        ]);

        DB::table('security.cspe_consultants')->insert([
            'id' => $cspeConsultantId,
            'person_id' => $personCspeId,
        ]);

        // 5. ¡La magia de la consola para nuestras pruebas en Angular!
        $this->command->info("\n✅ Datos de prueba creados exitosamente bajo la nueva Arquitectura.");
        $this->command->info("===============================================================");
        $this->command->info(" Copia este ID para probar el Autocompletado (Momento 1) en Angular:");
        $this->command->info(" PERSONA_ID (Consultor Funcional) : " . $personFuncId);
        $this->command->info("===============================================================\n");
    }
}