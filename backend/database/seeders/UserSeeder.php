<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Security\Models\User; 


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
    // 1. Condición de búsqueda en la base de datos
      ['email' => 'admin@ecosgrti.com'], 
      
      // 2. Datos a crear o actualizar
      [
          'name' => 'Administrador del Sistema',
          'password' => bcrypt('admin123'),
          'email_verified_at' => now(),
          // ... cualquier otro campo que necesites llenar
      ]);
    }
}