<?php

namespace Database\Factories;

use App\Enums\FulfilmentType;
use App\Enums\PinStatus;
use App\Models\ResultCheckerPin;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultCheckerPin>
 */
class ResultCheckerPinFactory extends Factory
{
    /**
     * An available 12-digit PIN for a result checker service.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pin = fake()->unique()->numerify('############');

        return [
            'service_id' => Service::factory()->state(['fulfilment_type' => FulfilmentType::Instant, 'price' => 650_000]),
            'pin' => $pin,
            'serial_number' => fake()->bothify('WRN#########'),
            'pin_hash' => ResultCheckerPin::hash($pin),
            'status' => PinStatus::Available,
        ];
    }
}
