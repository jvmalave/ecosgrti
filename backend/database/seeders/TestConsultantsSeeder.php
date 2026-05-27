<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class TestConsultantsSeeder extends Seeder
{
    public function run()
    {
        // 1. Generamos los UUIDs en memoria
        $socId = Str::uuid();
        $sysId = Str::uuid();
        $unitId = Str::uuid();
        
        $personFuncId = Str::uuid();
        $personCspeId = Str::uuid();
        
        $funcConsultantId = Str::uuid();
        $cspeConsultantId = Str::uuid();

        // 2. Insertamos los catálogos organizacionales (con datos básicos)
        DB::table('security.societies')->insert(['id' => $socId, 'name' => 'Sociedad de Prueba C.A.']);
        DB::table('security.systems')->insert(['id' => $sysId, 'name' => 'Sistema ERP Core']);
        DB::table('security.requesting_units')->insert(['id' => $unitId, 'name' => 'Dirección de Finanzas']);

        // 3. Insertamos las Personas
        DB::table('security.persons')->insert(['id' => $personFuncId, 'name' => 'Carlos Funcional']);
        DB::table('security.persons')->insert(['id' => $personCspeId, 'name' => 'Ana CSPE']);

        // 4. Insertamos los Consultores usando los UUIDs que acabamos de crear
        DB::table('security.functional_consultants')->insert([
            'id' => $funcConsultantId,
            'person_id' => $personFuncId,
            'society_id' => $socId,
            'system_id' => $sysId,
            'requesting_unit_id' => $unitId,
        ]);

        DB::table('security.cspe_consultants')->insert([
            'id' => $cspeConsultantId,
            'person_id' => $personCspeId,
        ]);

        // 5. ¡La magia! Imprimimos los UUIDs en la consola para ti
        $this->command->info("\n✅ Datos de prueba creados exitosamente en tu Base de Datos.");
        $this->command->info("===============================================================");
        $this->command->info(" Copia estos UUIDs y pégalos como Values en Insomnia:");
        $this->command->info(" functional_consultant_id : " . $funcConsultantId);
        $this->command->info(" cspe_consultants[0]      : " . $cspeConsultantId);
        $this->command->info("===============================================================\n");
    }
}