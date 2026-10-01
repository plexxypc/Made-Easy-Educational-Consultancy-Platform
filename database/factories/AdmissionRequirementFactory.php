<?php

namespace Database\Factories;

use App\Models\AdmissionRequirement;
use App\Models\Course;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionRequirement>
 */
class AdmissionRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'course_id' => Course::factory(),
            'cut_off_mark' => fake()->numberBetween(140, 300),
            'olevel_credits_required' => 5,
        ];
    }
}
