<?php

namespace App\Filament\Resources\Institutions;

use App\Enums\InstitutionOwnership;
use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Filament\Resources\Institutions\Pages\ManageInstitutions;
use App\Filament\Support\NameKeyInput;
use App\Models\Institution;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Institutions for the course & cut-off search: staff and admins
 * (InstitutionPolicy). Plain CRUD on one page; cut-off marks are managed
 * under Admission requirements (CSV import).
 */
class InstitutionResource extends Resource
{
    protected static ?string $model = Institution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Course search';

    protected static ?string $navigationLabel = 'Institutions';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Type, ownership and state are required here; only the CSV import can
     * create an institution without them.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                NameKeyInput::make(Institution::class)
                    ->columnSpanFull()
                    ->rule(fn (?Institution $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $owner = Institution::by_alias_key()[Institution::name_key((string) $value)] ?? null;

                        if ($owner !== null && $owner->id !== $record?->id) {
                            $fail("That name is already an alias of {$owner->name}.");
                        }
                    }),
                TagsInput::make('aliases')
                    ->label('Also known as')
                    ->placeholder('Add an alias, e.g. UNILAG')
                    ->helperText('Abbreviations or other names this institution appears under. Used only when matching names in the CSV import, so "UNILAG" lines update this institution instead of creating a new one.')
                    ->splitKeys(['Tab', ','])
                    ->nestedRecursiveRules(['string', 'max:255'])
                    ->rule(fn (?Institution $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        foreach ((array) $value as $alias) {
                            $owner = Institution::claiming_name((string) $alias, $record?->id);

                            if ($owner !== null) {
                                $fail("\"{$alias}\" is already used by {$owner->name}.");

                                return;
                            }
                        }
                    })
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(InstitutionType::class)
                    ->required(),
                Select::make('ownership')
                    ->options(InstitutionOwnership::class)
                    ->required(),
                Select::make('state')
                    ->options(NigerianState::class)
                    ->searchable()
                    ->required(),
            ]);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Institution $record): ?string => $record->aliases ? 'Also: '.implode(', ', $record->aliases) : null)
                    ->wrap(),
                TextColumn::make('type')
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('ownership')
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('state')
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('admission_requirements_count')
                    ->label('Courses')
                    ->counts('admissionRequirements')
                    ->badge()
                    ->sortable(),
                IconColumn::make('missing_details')
                    ->label('Details')
                    ->state(fn (Institution $record): bool => ! $record->isMissingDetails())
                    ->boolean()
                    ->tooltip(fn (Institution $record): ?string => $record->isMissingDetails() ? 'Type, ownership or state not set (created by a CSV import).' : null),
            ])
            ->filters([
                SelectFilter::make('type')->options(InstitutionType::class),
                SelectFilter::make('ownership')->options(InstitutionOwnership::class),
                SelectFilter::make('state')->options(NigerianState::class)->searchable(),
                Filter::make('missing_details')
                    ->label('Missing type, ownership or state')
                    ->query(fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
                        ->whereNull('type')
                        ->orWhereNull('ownership')
                        ->orWhereNull('state'))),
            ])
            ->recordActions([
                EditAction::make(),
                // Hidden by the policy while the institution has admission requirements.
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageInstitutions::route('/'),
        ];
    }
}
