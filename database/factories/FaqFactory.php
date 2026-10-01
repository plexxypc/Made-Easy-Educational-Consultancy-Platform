<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => rtrim(fake()->unique()->sentence(6), '.').'?',
            'answer' => fake()->paragraph(),
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    /**
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
