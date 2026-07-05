<?php

namespace Database\Factories\Workflow;

use App\Domains\Workflow\Models\RequirementPhaseHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RequirementPhaseHistoryFactory extends Factory
{
    protected $model = RequirementPhaseHistory::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid(),
            'phase_status_code' => 'RC',
            'transitioned_at' => now(),
            'executed_by_user_id' => Str::uuid(), 
            'remarks' => 'Prueba unitaria',
        ];
    }
}