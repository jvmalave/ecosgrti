<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Llamamos a nuestro seeder de usuarios y catalogos
        $this->call([
            UserSeeder::class,
            CatalogsSeeder::class,
            TestConsultantsSeeder::class,
            WorkflowSeeder::class,
        ]);
    }
}