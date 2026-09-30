{{-- Consultation Appointments: $upcoming, $past (ConsultationBooking with slot and order). --}}
@if ($upcoming->isEmpty() && $past->isEmpty())
    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm">
        <p class="text-slate-600">No consultations booked.</p>
        <x-public.button :href="route('book-consultation')" class="mt-4">Book a consultation</x-public.button>
    </div>
@else
    @foreach (['upcoming' => ['Upcoming', $upcoming], 'past' => ['Past', $past]] as $section_key => [$section_title, $bookings])
        <section @class(['mt-10' => $section_key === 'past']) aria-labelledby="{{ $section_key }}-heading">
            <h2 id="{{ $section_key }}-heading" class="text-xl font-bold text-slate-900">{{ $section_title }}</h2>
            @if ($bookings->isEmpty())
                <p class="mt-2 text-slate-600">{{ $section_key === 'upcoming' ? 'No upcoming consultations.' : 'No past consultations.' }}</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($bookings as $booking)
                        <li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="text-lg font-semibold text-slate-900">{{ $booking->slot->dateLabel() }}</p>
                                    <p class="text-slate-700">{{ $booking->slot->timeLabel() }} · {{ $booking->slot->consultation_type->getLabel() }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $booking->statusLabel() }}</span>
                            </div>
                            @if ($booking->slot->consultation_type->needsMeetingLink() && $booking->status === \App\Enums\ConsultationBookingStatus::Confirmed)
                                <p class="mt-3 text-sm">
                                    <span class="font-semibold text-slate-600">Meeting link:</span>
                                    @if (filled($booking->meeting_link))
                                        <a href="{{ $booking->meeting_link }}" class="break-all font-semibold text-brand-700 underline" target="_blank" rel="noopener noreferrer">{{ $booking->meeting_link }}</a>
                                    @else
                                        <span class="rounded border border-dashed border-amber-400 bg-amber-50 px-2 py-0.5 font-semibold text-amber-900">To be added by our team</span>
                                    @endif
                                </p>
                            @endif
                            <p class="mt-3 text-sm">
                                <a href="{{ route('orders.track.show', $booking->order) }}" class="font-semibold text-brand-700 underline">View booking {{ $booking->order->display_reference }}</a>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
@endif
