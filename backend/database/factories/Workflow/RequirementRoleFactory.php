<?php

namespace Database\Factories\Workflow;

use App\Domains\Workflow\Models\RequirementRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequirementRoleFactory extends Factory
{
    protected $model = RequirementRole::class;

    public function definition(): array
    {
        return [
            // requirement_id se asume inyectado desde la prueba
            'role_name' => $this->faker->jobTitle(),
            'description' => $this->faker->sentence(),
            'assignment_type' => $this->faker->randomElement(['Dedicación Exclusiva', 'Tiempo Parcial', 'Bajo Demanda']),
        ];
    }
}