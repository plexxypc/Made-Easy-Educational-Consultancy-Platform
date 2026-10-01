<?php

namespace Database\Factories;

use App\Enums\TestimonialStatus;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    /**
     * Pending, not linked to an order.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => fake()->firstName().' '.fake()->randomLetter().'.',
            'body' => fake()->paragraph(),
            'rating' => fake()->optional()->numberBetween(1, Testimonial::MAX_RATING),
            'status' => TestimonialStatus::Pending,
        ];
    }

    /**
     * @return static
     */
    public function approved(): static
    {
        return $this->state(['status' => TestimonialStatus::Approved, 'moderated_at' => now()]);
    }

    /**
     * @return static
     */
    public function rejected(): static
    {
        return $this->state(['status' => TestimonialStatus::Rejected, 'moderated_at' => now()]);
    }
}
