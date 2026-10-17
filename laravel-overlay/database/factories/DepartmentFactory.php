<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        return ['organization_id' => Organization::factory(), 'name' => 'Division ' . fake()->unique()->numerify('####'), 'is_active' => true];
    }
}
