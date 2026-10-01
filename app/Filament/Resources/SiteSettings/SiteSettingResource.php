<?php

namespace App\Filament\Resources\SiteSettings;

use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Site settings (admins only, SiteSettingPolicy): editable site copy such as
 * the homepage "students assisted" figure and the About Us text. One fixed
 * row per SiteSettingKey; changes show on the next page load.
 */
class SiteSettingResource extends Resource
{
    protected static ?string $model = SiteSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $modelLabel = 'site setting';

    protected static ?int $navigationSort = 90;

    /**
     * One "value" field, shaped by the setting being edited. Empty = default.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->label(fn (?SiteSetting $record): string => (string) $record?->key->getLabel())
                    ->helperText(fn (?SiteSetting $record): ?string => $record?->key->help())
                    ->rules(fn (?SiteSetting $record): array => $record?->key->rules() ?? [])
                    ->validationMessages(['regex' => 'Use a whole number, optionally with commas and a trailing "+", e.g. 100+ or 1,250.'])
                    ->visible(fn (?SiteSetting $record): bool => ! ($record?->key->isLongText() ?? false)),
                Textarea::make('value')
                    ->label(fn (?SiteSetting $record): string => (string) $record?->key->getLabel())
                    ->helperText(fn (?SiteSetting $record): ?string => $record?->key->help())
                    ->rules(fn (?SiteSetting $record): array => $record?->key->rules() ?? [])
                    ->rows(14)
                    ->visible(fn (?SiteSetting $record): bool => $record?->key->isLongText() ?? false),
            ]);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('key')
                    ->label('Setting'),
                TextColumn::make('value')
                    ->label('Shown on the site')
                    ->state(fn (SiteSetting $record): ?string => $record->editableValue())
                    ->formatStateUsing(fn (?string $state): string => Str::limit((string) $state, 90))
                    ->description(fn (SiteSetting $record): ?string => blank($record->value) ? ($record->key->default() === null ? 'Not set: hidden on the site' : 'Default text') : null)
                    ->placeholder('Not set: hidden on the site')
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->since()
                    ->description(fn (SiteSetting $record): ?string => $record->updatedBy?->name),
            ])
            ->recordActions([
                EditAction::make()
                    ->fillForm(fn (SiteSetting $record): array => ['value' => $record->editableValue()])
                    ->using(function (SiteSetting $record, array $data): SiteSetting {
                        $value = trim((string) ($data['value'] ?? ''));

                        // Unchanged default text is stored as "use the default", so improved defaults still apply.
                        $record->forceFill([
                            'value' => $value === '' || $value === $record->key->default() ? null : $value,
                            'updated_by' => auth()->id(),
                        ])->save();

                        return $record;
                    }),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageSiteSettings::route('/'),
        ];
    }
}
