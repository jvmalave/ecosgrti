<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tipos de Requerimiento
        $reqTypes = ['Nuevo', 'Evolutivo', 'Correctivo'];
        foreach ($reqTypes as $type) {
            DB::table('catalogs.requirement_types')->insert([
                'id' => Str::uuid()->toString(),
                'name' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Tipos de Gestión
        $mngTypes = ['Roles', 'Entregable', 'Mixto'];
        foreach ($mngTypes as $type) {
            DB::table('catalogs.management_types')->insert([
                'id' => Str::uuid()->toString(),
                'name' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // NOTA: Aquí también puedes agregar código en el futuro para sembrar 
        // Sociedades, Sistemas y Unidades de prueba si lo deseas.
    }
}