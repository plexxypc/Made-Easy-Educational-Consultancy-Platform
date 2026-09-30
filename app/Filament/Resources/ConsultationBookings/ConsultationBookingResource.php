<?php

namespace App\Filament\Resources\ConsultationBookings;

use App\Enums\ConsultationBookingStatus;
use App\Filament\Resources\ConsultationBookings\Pages\ListConsultationBookings;
use App\Filament\Resources\ConsultationBookings\Pages\ViewConsultationBooking;
use App\Filament\Resources\ConsultationBookings\Schemas\ConsultationBookingInfolist;
use App\Filament\Resources\ConsultationBookings\Tables\ConsultationBookingsTable;
use App\Models\ConsultationBooking;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Staff + admin (ConsultationBookingPolicy). Bookings are created by
 * customers on /book-consultation; this screen only views them and runs the
 * ConsultationBookingActions (complete, no-show, cancel, reschedule, link).
 */
class ConsultationBookingResource extends Resource
{
    protected static ?string $model = ConsultationBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Consultations';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $modelLabel = 'consultation booking';

    protected static ?int $navigationSort = 10;

    /**
     * @param  Schema  $schema
     * @return Schema
     */
    public static function infolist(Schema $schema): Schema
    {
        return ConsultationBookingInfolist::configure($schema);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return ConsultationBookingsTable::configure($table);
    }

    /**
     * @return Builder<ConsultationBooking>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['slot', 'order.user', 'order.service']);
    }

    /**
     * Badge: paid bookings waiting for a new time.
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        $count = ConsultationBooking::query()
            ->where('status', ConsultationBookingStatus::PendingPayment->value)
            ->whereNotNull('needs_rebooking_at')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * @param  Model|null  $record
     * @return string
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof ConsultationBooking ? "Consultation for order {$record->order->display_reference}" : 'Consultation booking';
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListConsultationBookings::route('/'),
            'view' => ViewConsultationBooking::route('/{record}'),
        ];
    }
}
