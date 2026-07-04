<?php

namespace Database\Factories\Core;

use App\Domains\Core\Models\Requirement;
use App\Domains\Security\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RequirementFactory extends Factory
{
    /**
     * Vinculamos explícitamente este factory con el modelo de tu dominio.
     */
    protected $model = Requirement::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            // Simulamos el formato del RRTI para que sea único en cada prueba
            'rrti' => 'RRTI-' . $this->faker->unique()->numerify('####-#####'), 
            'requirement_type' => $this->faker->randomElement(['Nuevo', 'Mejora', 'Corrección']),
            'creation_date' => now(),
            'description' => $this->faker->paragraph(),
            'management_type' => 'Mixto', // Valor por defecto para pruebas
            'snapshot_society_name' => 'CANTV',
            'snapshot_system_name' => 'SGRTI',
            'snapshot_unit_name' => 'CSPE',
            
            // Genera un usuario automáticamente para satisfacer la llave foránea
            'functional_consultant_id' => User::factory(), 
            
            'status' => 'PL_OPEN',
            'is_locked' => false,
            'progress_percentage' => 4.00,
        ];
    }
}