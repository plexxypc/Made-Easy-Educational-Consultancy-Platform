<?php

namespace App\Filament\Resources\AdmissionRequirements;

use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Filament\Resources\AdmissionRequirements\Pages\ManageAdmissionRequirements;
use App\Models\AdmissionRequirement;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Cut-off marks and O'Level credit requirements per institution and course:
 * staff and admins (AdmissionRequirementPolicy). Bulk entry is by CSV
 * import (ManageAdmissionRequirements); single rows can be fixed here.
 */
class AdmissionRequirementResource extends Resource
{
    protected static ?string $model = AdmissionRequirement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Course search';

    protected static ?string $navigationLabel = 'Cut-off marks';

    protected static ?string $modelLabel = 'admission requirement';

    protected static ?int $navigationSort = 10;

    /**
     * @param  Schema  $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('institution_id')
                    ->label('Institution')
                    ->relationship('institution', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('institution_id', $get('institution_id')),
                    )
                    ->validationMessages(['unique' => 'This institution already has a cut-off mark for that course. Edit that row instead.']),
                TextInput::make('cut_off_mark')
                    ->label('JAMB cut-off mark')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(AdmissionRequirement::MAX_SCORE)
                    ->required(),
                TextInput::make('olevel_credits_required')
                    ->label("O'Level credits required")
                    ->integer()
                    ->minValue(1)
                    ->maxValue(AdmissionRequirement::MAX_CREDITS)
                    ->default(5)
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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['institution', 'course']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('institution.name')
                    ->label('Institution')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('course.name')
                    ->label('Course')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('cut_off_mark')
                    ->label('Cut-off')
                    ->sortable(),
                TextColumn::make('olevel_credits_required')
                    ->label('Credits')
                    ->sortable(),
                TextColumn::make('institution.state')
                    ->label('State')
                    ->placeholder('Not set'),
                TextColumn::make('updated_at')
                    ->label('Last changed')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('institution_id')
                    ->label('Institution')
                    ->relationship('institution', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('state')
                    ->options(NigerianState::class)
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('institution', fn (Builder $query) => $query->where('state', $data['value']))
                        : $query),
                SelectFilter::make('type')
                    ->label('Institution type')
                    ->options(InstitutionType::class)
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('institution', fn (Builder $query) => $query->where('type', $data['value']))
                        : $query),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Admin only (deleteAny), e.g. to clear the sample data before go-live.
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageAdmissionRequirements::route('/'),
        ];
    }
}
