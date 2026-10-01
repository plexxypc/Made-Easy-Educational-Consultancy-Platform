<?php

namespace Database\Factories;

use App\Enums\InstitutionOwnership;
use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'University of '.Str::title(fake()->unique()->word()).' '.fake()->unique()->numberBetween(1, 99999),
            'type' => InstitutionType::University,
            'ownership' => fake()->randomElement(InstitutionOwnership::cases()),
            'state' => fake()->randomElement(NigerianState::cases()),
        ];
    }
}
