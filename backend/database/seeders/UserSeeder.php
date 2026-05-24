<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Security\Models\User; 
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrador del Sistema',
            'email' => 'admin@ecosgrti.com',
            // Usamos Hash::make para encriptar la contraseña correctamente
            'password' => Hash::make('admin123'), 
        ]);
    }
}