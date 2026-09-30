<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Enums\CustomerFieldType;
use App\Enums\FulfilmentType;
use App\Filament\Support\SlugField;
use App\Models\Service;
use App\Support\Money;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    /**
     * Service form. Prices are typed in naira and stored as kobo.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('Possible duplicate')
                    ->warning()
                    ->columnSpanFull()
                    ->description(fn (?Service $record): string => "The requirements list this service twice. The other entry is \"{$record?->possibleDuplicateSlug()}\". Confirm with the client whether to keep both, then deactivate or rename one.")
                    ->visible(fn (?Service $record): bool => $record?->possibleDuplicateSlug() !== null),
                Section::make('Service')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('service_category_id')
                            ->label('Category')
                            ->relationship('category', 'name', fn ($query) => $query->orderBy('sort_order'))
                            ->required()
                            ->preload(),
                        Select::make('fulfilment_type')
                            ->label('Fulfilment')
                            ->options(FulfilmentType::class)
                            ->required()
                            ->default(FulfilmentType::Manual->value)
                            ->live()
                            ->helperText('Instant: delivered automatically after payment. Manual: fixed price, handled by staff. Quote: staff set the price per order. Consultation booking: customers pick a slot on /book-consultation and pay.'),
                        SlugField::name_input(),
                        SlugField::slug_input(),
                        self::price_input(),
                        TextInput::make('pin_low_stock_threshold')
                            ->label('Low PIN stock at')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(100000)
                            ->suffix('PINs or fewer')
                            ->placeholder(fn (): string => (string) config('madeeasy.pins.low_stock_threshold'))
                            ->helperText(fn (): string => 'Staff are alerted (bell + email) when available PINs drop to this level, and again at zero. Leave empty to use the default ('.config('madeeasy.pins.low_stock_threshold').').')
                            ->visible(fn (Get $get): bool => self::fulfilment_type($get) === FulfilmentType::Instant),
                        Toggle::make('is_active')
                            ->label('Active (customers can order it)')
                            ->default(false)
                            ->inline(false),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Information required from the customer')
                    ->description('Extra fields on this service\'s request form. Name, phone and email are always asked, so don\'t add them here. File fields accept PDF, JPG or PNG. Changing fields does not alter answers on existing orders.')
                    ->columnSpanFull()
                    ->schema([
                        self::customer_fields_repeater(),
                    ]),
            ]);
    }

    /**
     * Naira price input backed by the integer-kobo `price` column.
     *
     * @return TextInput
     */
    private static function price_input(): TextInput
    {
        return TextInput::make('price')
            ->label('Price')
            ->prefix('₦')
            ->inputMode('decimal')
            ->default('0')
            ->regex(Money::NAIRA_INPUT_REGEX)
            ->validationMessages(['regex' => 'Enter an amount in naira, e.g. 6500 or 6500.50.'])
            ->required(fn (Get $get): bool => ! self::is_quote($get))
            ->disabled(fn (Get $get): bool => self::is_quote($get))
            ->formatStateUsing(fn (mixed $state): ?string => is_int($state) ? Money::kobo_to_naira($state) : $state)
            ->dehydrateStateUsing(fn (mixed $state): int => Money::naira_to_kobo($state))
            ->helperText(function (Get $get, ?Service $record): ?string {
                if (self::is_quote($get)) {
                    return 'Quote services are always ₦0 here; an admin sets each order\'s total.';
                }

                if ($record?->isInactivePlaceholder()) {
                    return 'Placeholder price from the initial seed. Set the real price before activating.';
                }

                return null;
            });
    }

    /**
     * Builder for Service::$customer_fields (shape: see create_services_table migration).
     *
     * @return Repeater
     */
    private static function customer_fields_repeater(): Repeater
    {
        return Repeater::make('customer_fields')
            ->hiddenLabel()
            ->default([])
            ->columns(4)
            ->reorderable()
            ->collapsible()
            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
            ->addActionLabel('Add field')
            ->schema([
                TextInput::make('label')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        $current_key = (string) $get('key');

                        if ($current_key !== '' && $current_key !== Str::snake((string) $old)) {
                            return;
                        }

                        $set('key', Str::snake(Str::ascii((string) $state)));
                    })
                    ->columnSpan(2),
                TextInput::make('key')
                    ->required()
                    ->maxLength(50)
                    ->regex('/^[a-z][a-z0-9_]*$/')
                    ->validationMessages(['regex' => 'Lowercase letters, numbers and underscores, starting with a letter.'])
                    ->distinct()
                    ->helperText('Internal name. Don\'t rename once orders exist.')
                    ->columnSpan(2),
                Select::make('type')
                    ->options(CustomerFieldType::class)
                    ->required()
                    ->default(CustomerFieldType::Text->value)
                    ->live()
                    ->columnSpan(2),
                Toggle::make('required')
                    ->default(true)
                    ->inline(false),
                TagsInput::make('options')
                    ->label('Choices')
                    ->placeholder('Type a choice and press Enter')
                    ->required()
                    ->visible(fn (Get $get): bool => self::field_type($get) === CustomerFieldType::Select)
                    ->columnSpanFull(),
                TextInput::make('min')
                    ->label('Minimum')
                    ->integer()
                    ->visible(fn (Get $get): bool => self::field_type($get) === CustomerFieldType::Number)
                    ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) ? (int) $state : null),
                TextInput::make('max')
                    ->label('Maximum')
                    ->integer()
                    ->gte('min')
                    ->visible(fn (Get $get): bool => self::field_type($get) === CustomerFieldType::Number)
                    ->dehydrateStateUsing(fn (mixed $state): ?int => filled($state) ? (int) $state : null),
            ]);
    }

    /**
     * Whether the form's fulfilment type is "quote".
     *
     * @param  Get  $get
     * @return bool
     */
    private static function is_quote(Get $get): bool
    {
        return self::fulfilment_type($get) === FulfilmentType::Quote;
    }

    /**
     * The form's selected fulfilment type.
     *
     * @param  Get  $get
     * @return FulfilmentType|null
     */
    private static function fulfilment_type(Get $get): ?FulfilmentType
    {
        $fulfilment_type = $get('fulfilment_type');

        return $fulfilment_type instanceof FulfilmentType ? $fulfilment_type : FulfilmentType::tryFrom((string) $fulfilment_type);
    }

    /**
     * The type selected in the current repeater item.
     *
     * @param  Get  $get
     * @return CustomerFieldType|null
     */
    private static function field_type(Get $get): ?CustomerFieldType
    {
        $field_type = $get('type');

        return $field_type instanceof CustomerFieldType ? $field_type : CustomerFieldType::tryFrom((string) $field_type);
    }
}
