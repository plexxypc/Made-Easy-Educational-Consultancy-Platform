{{-- Consultation Appointments: $upcoming, $past (ConsultationBooking with slot and order). --}}
@if ($upcoming->isEmpty() && $past->isEmpty())
    <x-public.empty-state title="No consultations booked." illustration="empty-consultations">
        <x-slot:action>
            <x-public.button :href="route('book-consultation')">Book a consultation</x-public.button>
        </x-slot:action>
    </x-public.empty-state>
@else
    @foreach (['upcoming' => ['Upcoming', $upcoming], 'past' => ['Past', $past]] as $section_key => [$section_title, $bookings])
        <section @class(['mt-10' => $section_key === 'past']) aria-labelledby="{{ $section_key }}-heading">
            <h2 id="{{ $section_key }}-heading" class="text-xl font-bold text-ink">{{ $section_title }}</h2>
            @if ($bookings->isEmpty())
                <p class="mt-2 text-ink/70">{{ $section_key === 'upcoming' ? 'No upcoming consultations.' : 'No past consultations.' }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($bookings as $booking)
                        <li class="rounded-2xl border border-ink/20 bg-white p-5">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-display text-lg font-bold text-ink">{{ $booking->slot->dateLabel() }}</p>
                                    <p class="text-ink/80">{{ $booking->slot->timeLabel() }} · {{ $booking->slot->consultation_type->getLabel() }}</p>
                                </div>
                                <span class="rounded-full bg-ink/5 px-3 py-1 text-sm font-semibold text-ink/80 ring-1 ring-inset ring-ink/15">{{ $booking->statusLabel() }}</span>
                            </div>
                            @if ($booking->slot->consultation_type->needsMeetingLink() && $booking->status === \App\Enums\ConsultationBookingStatus::Confirmed)
                                <p class="mt-3 text-sm">
                                    <span class="font-semibold text-ink/70">Meeting link:</span>
                                    @if (filled($booking->meeting_link))
                                        <a href="{{ $booking->meeting_link }}" class="break-all font-semibold text-accent-700 underline" target="_blank" rel="noopener noreferrer">{{ $booking->meeting_link }}</a>
                                    @else
                                        <span class="rounded border border-dashed border-amber-400 bg-amber-50 px-2 py-0.5 font-semibold text-amber-900">To be added by our team</span>
                                    @endif
                                </p>
                            @endif
                            <p class="mt-3 text-sm">
                                <a href="{{ route('orders.track.show', $booking->order) }}" class="font-semibold text-accent-700 underline">View booking {{ $booking->order->display_reference }}</a>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
@endif
