<?php

namespace App\Actions\Consultations;

use App\Enums\ConsultationType;
use App\Exceptions\BusinessRuleException;
use App\Models\ConsultationSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Facades\DB;

/**
 * Add consultation availability (admin only): one slot, or a simple weekly
 * pattern ("every Mon–Fri, 10am–4pm, hourly, for the next 4 weeks").
 *
 * Business rules:
 *  - Times are in the consultation timezone (Africa/Lagos by default).
 *  - A slot must start in the future.
 *  - No duplicates: a slot with the same date, start time and type that
 *    isn't cancelled already exists -> refused (single) or skipped (pattern).
 *    Assumption: two slots of DIFFERENT types at the same time are allowed
 *    (e.g. two consultants); use capacity for several people in one slot.
 *  - A pattern may create at most config('madeeasy.consultations.max_slots_per_batch').
 */
class CreateConsultationSlots
{
    public const MIN_DURATION_MINUTES = 15;

    public const MAX_DURATION_MINUTES = 480;

    public const MAX_CAPACITY = 50;

    /**
     * @param  string  $date  "YYYY-MM-DD".
     * @param  string  $start_time  "HH:MM".
     * @param  int  $duration_minutes
     * @param  ConsultationType  $consultation_type
     * @param  int  $capacity
     * @param  User  $admin
     * @return ConsultationSlot
     *
     * @throws BusinessRuleException
     */
    public function create_one(string $date, string $start_time, int $duration_minutes, ConsultationType $consultation_type, int $capacity, User $admin): ConsultationSlot
    {
        self::authorise_and_validate($admin, $duration_minutes, $capacity);

        $starts_at = self::parse($date, $start_time);

        if ($starts_at->lessThanOrEqualTo(now())) {
            throw new BusinessRuleException('Choose a date and time in the future.');
        }

        if (self::exists($starts_at, $consultation_type)) {
            throw new BusinessRuleException("There is already a {$consultation_type->getLabel()} slot at that time.");
        }

        return self::make($starts_at, $duration_minutes, $consultation_type, $capacity, $admin);
    }

    /**
     * @param  string  $first_date  "YYYY-MM-DD": the pattern starts on this day.
     * @param  int  $weeks  How many weeks to repeat (1–26).
     * @param  array<int, int>  $weekdays  ISO days: 1 = Monday … 7 = Sunday.
     * @param  string  $from_time  "HH:MM": first slot of each day.
     * @param  string  $until_time  "HH:MM": the last slot must END by then.
     * @param  int  $duration_minutes  Also the gap between slot starts.
     * @param  ConsultationType  $consultation_type
     * @param  int  $capacity
     * @param  User  $admin
     * @return array{created: int, skipped: int}
     *
     * @throws BusinessRuleException
     */
    public function create_recurring(string $first_date, int $weeks, array $weekdays, string $from_time, string $until_time, int $duration_minutes, ConsultationType $consultation_type, int $capacity, User $admin): array
    {
        self::authorise_and_validate($admin, $duration_minutes, $capacity);

        if ($weeks < 1 || $weeks > 26) {
            throw new BusinessRuleException('Repeat for between 1 and 26 weeks.');
        }

        $weekdays = array_values(array_unique(array_map('intval', $weekdays)));

        if ($weekdays === [] || array_diff($weekdays, range(1, 7)) !== []) {
            throw new BusinessRuleException('Choose at least one day of the week.');
        }

        $first_day = self::parse($first_date, '00:00');
        $starts = [];

        for ($day = $first_day; $day->lessThan($first_day->addWeeks($weeks)); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeekIso, $weekdays, true)) {
                continue;
            }

            $day_end = self::parse($day->toDateString(), $until_time);

            for ($start = self::parse($day->toDateString(), $from_time); $start->addMinutes($duration_minutes)->lessThanOrEqualTo($day_end); $start = $start->addMinutes($duration_minutes)) {
                $starts[] = $start;
            }
        }

        if ($starts === []) {
            throw new BusinessRuleException('That pattern gives no slots: check the days and that the end time is after the start time plus one slot.');
        }

        $max_slots = (int) config('madeeasy.consultations.max_slots_per_batch');

        if (count($starts) > $max_slots) {
            throw new BusinessRuleException('That pattern would add '.count($starts)." slots; add at most {$max_slots} at a time.");
        }

        return DB::transaction(function () use ($starts, $duration_minutes, $consultation_type, $capacity, $admin): array {
            $created = 0;

            foreach ($starts as $starts_at) {
                if ($starts_at->lessThanOrEqualTo(now()) || self::exists($starts_at, $consultation_type)) {
                    continue;
                }

                self::make($starts_at, $duration_minutes, $consultation_type, $capacity, $admin);
                $created++;
            }

            return ['created' => $created, 'skipped' => count($starts) - $created];
        });
    }

    /**
     * @param  User  $admin
     * @param  int  $duration_minutes
     * @param  int  $capacity
     * @return void
     *
     * @throws BusinessRuleException
     */
    private static function authorise_and_validate(User $admin, int $duration_minutes, int $capacity): void
    {
        if (! $admin->isAdmin()) {
            throw new BusinessRuleException('Only admins can add consultation slots.');
        }

        if ($duration_minutes < self::MIN_DURATION_MINUTES || $duration_minutes > self::MAX_DURATION_MINUTES) {
            throw new BusinessRuleException('A slot must last between 15 minutes and 8 hours.');
        }

        if ($capacity < 1 || $capacity > self::MAX_CAPACITY) {
            throw new BusinessRuleException('Capacity must be between 1 and 50.');
        }
    }

    /**
     * A date and "HH:MM" in the consultation timezone.
     *
     * @param  string  $date
     * @param  string  $time
     * @return CarbonImmutable
     *
     * @throws BusinessRuleException
     */
    private static function parse(string $date, string $time): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d H:i', substr($date, 0, 10).' '.substr($time, 0, 5), ConsultationSlot::timezone());
        } catch (InvalidFormatException) {
            $parsed = null;
        }

        if (! $parsed instanceof CarbonImmutable) {
            throw new BusinessRuleException('Enter a valid date and time.');
        }

        return $parsed;
    }

    /**
     * Whether a live (not cancelled) slot of that type starts then.
     *
     * @param  CarbonImmutable  $starts_at
     * @param  ConsultationType  $consultation_type
     * @return bool
     */
    private static function exists(CarbonImmutable $starts_at, ConsultationType $consultation_type): bool
    {
        return ConsultationSlot::query()
            ->whereNull('cancelled_at')
            ->whereDate('date', $starts_at->toDateString())
            ->where('start_time', $starts_at->format('H:i:s'))
            ->where('consultation_type', $consultation_type->value)
            ->exists();
    }

    /**
     * @param  CarbonImmutable  $starts_at
     * @param  int  $duration_minutes
     * @param  ConsultationType  $consultation_type
     * @param  int  $capacity
     * @param  User  $admin
     * @return ConsultationSlot
     */
    private static function make(CarbonImmutable $starts_at, int $duration_minutes, ConsultationType $consultation_type, int $capacity, User $admin): ConsultationSlot
    {
        $slot = (new ConsultationSlot)->forceFill([
            'date' => $starts_at->toDateString(),
            'start_time' => $starts_at->format('H:i:s'),
            'duration_minutes' => $duration_minutes,
            'consultation_type' => $consultation_type,
            'capacity' => $capacity,
            'created_by' => $admin->id,
        ]);
        $slot->save();

        return $slot;
    }
}
