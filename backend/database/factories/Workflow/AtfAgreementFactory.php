<?php

declare(strict_types=1);

namespace Database\Factories\Workflow;

use App\Domains\Workflow\Models\AtfAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Domains\Security\Models\User;

class AtfAgreementFactory extends Factory
{
    protected $model = AtfAgreement::class;

    public function definition(): array
{
    return [
        'id' => Str::uuid()->toString(),
        'requirement_id' => null,
        'description' => $this->faker->sentence(),
        'agreement_date' => now(),
        'registered_by_user_id' => User::factory(), 
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
}