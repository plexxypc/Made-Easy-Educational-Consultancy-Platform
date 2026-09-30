<?php

namespace Database\Factories;

use App\Enums\ConsultationBookingStatus;
use App\Models\ConsultationBooking;
use App\Models\ConsultationSlot;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationBooking>
 */
class ConsultationBookingFactory extends Factory
{
    /**
     * A pending booking with a live hold.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->guest(),
            'consultation_slot_id' => ConsultationSlot::factory(),
            'status' => ConsultationBookingStatus::PendingPayment,
            'hold_expires_at' => now()->addMinutes(30),
        ];
    }

    /**
     * Confirmed (paid) booking.
     *
     * @return static
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ConsultationBookingStatus::Confirmed,
            'hold_expires_at' => null,
            'confirmed_at' => now(),
        ]);
    }
}
