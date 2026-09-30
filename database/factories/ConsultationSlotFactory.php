<?php

namespace Database\Factories;

use App\Enums\ConsultationType;
use App\Models\ConsultationSlot;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationSlot>
 */
class ConsultationSlotFactory extends Factory
{
    /**
     * A slot at 10am, three days from now (consultation timezone), 1 place.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now(ConsultationSlot::timezone())->addDays(3)->toDateString(),
            'start_time' => '10:00:00',
            'duration_minutes' => 60,
            'consultation_type' => ConsultationType::GoogleMeet,
            'capacity' => 1,
        ];
    }

    /**
     * Starting at the given moment.
     *
     * @param  DateTimeInterface  $starts_at
     * @return static
     */
    public function startingAt(DateTimeInterface $starts_at): static
    {
        $local = CarbonImmutable::instance($starts_at)->setTimezone(ConsultationSlot::timezone());

        return $this->state(fn (array $attributes): array => [
            'date' => $local->toDateString(),
            'start_time' => $local->format('H:i:s'),
        ]);
    }

    /**
     * Of the given type.
     *
     * @param  ConsultationType  $consultation_type
     * @return static
     */
    public function ofType(ConsultationType $consultation_type): static
    {
        return $this->state(fn (array $attributes): array => ['consultation_type' => $consultation_type]);
    }
}
