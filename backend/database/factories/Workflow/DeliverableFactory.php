<?php

namespace Database\Factories\Workflow;

use App\Domains\Workflow\Models\Deliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverableFactory extends Factory
{
    protected $model = Deliverable::class;

    public function definition(): array
    {
        return [
            // requirement_id se asume inyectado desde la prueba
            'name' => 'Documento de ' . $this->faker->word(),
            'description' => $this->faker->sentence(),
        ];
    }
}