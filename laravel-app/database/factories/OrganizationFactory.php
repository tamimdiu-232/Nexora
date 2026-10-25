<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Demo Org ' . fake()->unique()->numerify('####');
        return ['name' => $name, 'code' => Str::slug($name), 'is_active' => true];
    }
}
