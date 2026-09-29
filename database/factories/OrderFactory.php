<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'status' => OrderStatus::PendingPayment,
            'total_amount' => fn (array $attributes): int => Service::query()->findOrFail($attributes['service_id'])->price,
            'field_answers' => [],
        ];
    }

    /**
     * A guest order (no account).
     *
     * @return static
     */
    public function guest(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->numerify('080########'),
        ]);
    }

    /**
     * An order with the given status.
     *
     * @param  OrderStatus  $status
     * @return static
     */
    public function withStatus(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
