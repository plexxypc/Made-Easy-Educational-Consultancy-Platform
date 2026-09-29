<?php

namespace Database\Factories;

use App\Enums\FulfilmentType;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'service_category_id' => ServiceCategory::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'price' => fake()->numberBetween(10, 500) * 10_000, // kobo: ₦100 – ₦5,000
            'is_active' => true,
            'fulfilment_type' => FulfilmentType::Manual,
            'customer_fields' => [],
        ];
    }
}
