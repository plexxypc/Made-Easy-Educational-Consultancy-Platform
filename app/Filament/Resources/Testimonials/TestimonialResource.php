<?php

namespace App\Filament\Resources\Testimonials;

use App\Enums\TestimonialStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Testimonials\Pages\ManageTestimonials;
use App\Models\Testimonial;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * Testimonial moderation (PDF section 15): staff and admins approve or
 * reject (TestimonialPolicy). Only approved ones appear on the site; the
 * list opens on those waiting for review.
 */
class TestimonialResource extends Resource
{
    protected static ?string $model = Testimonial::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Testimonials';

    protected static ?int $navigationSort = 10;

    /**
     * Pending count in the menu, so new submissions get noticed.
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        $pending = Testimonial::query()->where('status', TestimonialStatus::Pending->value)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    /**
     * @return string
     */
    public static function getNavigationBadgeColor(): string
    {
        return 'warning';
    }

    /**
     * Used for typo fixes and for adding one received another way. The
     * status is changed with Approve/Reject only, so it is never edited here.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('customer_name')
                    ->label('Name shown')
                    ->required()
                    ->maxLength(80),
                Select::make('rating')
                    ->options(array_combine(range(1, Testimonial::MAX_RATING), array_map(fn (int $stars): string => str_repeat('★', $stars), range(1, Testimonial::MAX_RATING))))
                    ->placeholder('No rating'),
                Textarea::make('body')
                    ->label('Testimonial')
                    ->required()
                    ->maxLength(1000)
                    ->rows(6)
                    ->helperText("Fix typos only; don't change what the customer meant.")
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')
                    ->label('Testimonial')
                    ->limit(160)
                    ->wrap()
                    ->searchable()
                    ->description(fn (Testimonial $record): string => '— '.$record->customer_name.($record->rating ? ' · '.str_repeat('★', $record->rating) : '')),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('order.reference')
                    ->label('Order')
                    ->formatStateUsing(fn (Testimonial $record): ?string => $record->order?->display_reference)
                    ->url(fn (Testimonial $record): ?string => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null)
                    ->placeholder('Added by staff'),
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TestimonialStatus::class)
                    ->default(TestimonialStatus::Pending->value),
            ])
            ->recordActions([
                self::moderate_action(TestimonialStatus::Approved),
                self::moderate_action(TestimonialStatus::Rejected),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::moderate_bulk_action(TestimonialStatus::Approved),
                    self::moderate_bulk_action(TestimonialStatus::Rejected),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * "Approve" / "Reject" on one testimonial.
     *
     * @param  TestimonialStatus  $status
     * @return Action
     */
    private static function moderate_action(TestimonialStatus $status): Action
    {
        $approve = $status === TestimonialStatus::Approved;

        return Action::make($approve ? 'approve' : 'reject')
            ->label($approve ? 'Approve' : 'Reject')
            ->icon($approve ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
            ->color($approve ? 'success' : 'gray')
            ->visible(fn (Testimonial $record): bool => $record->status !== $status)
            ->authorize(fn (Testimonial $record): bool => Gate::allows('moderate', $record))
            ->requiresConfirmation($approve)
            ->modalHeading('Show this testimonial on the website?')
            ->modalDescription('It will appear on the homepage and the testimonials page straight away.')
            ->action(function (Testimonial $record) use ($status): void {
                $record->moderate($status, auth()->user());

                Notification::make()
                    ->success()
                    ->title($status === TestimonialStatus::Approved ? 'Approved: now shown on the website.' : 'Rejected: not shown on the website.')
                    ->send();
            });
    }

    /**
     * Bulk "Approve selected" / "Reject selected".
     *
     * @param  TestimonialStatus  $status
     * @return BulkAction
     */
    private static function moderate_bulk_action(TestimonialStatus $status): BulkAction
    {
        $approve = $status === TestimonialStatus::Approved;

        return BulkAction::make($approve ? 'approve_selected' : 'reject_selected')
            ->label($approve ? 'Approve selected' : 'Reject selected')
            ->icon($approve ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedXCircle)
            ->requiresConfirmation()
            ->authorize(fn (): bool => Gate::allows('viewAny', Testimonial::class))
            ->action(function (Collection $records) use ($status): void {
                $records->each(function (Testimonial $testimonial) use ($status): void {
                    if (Gate::allows('moderate', $testimonial)) {
                        $testimonial->moderate($status, auth()->user());
                    }
                });
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageTestimonials::route('/'),
        ];
    }
}
