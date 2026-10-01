<?php

namespace App\Filament\Resources\Faqs;

use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * FAQ (PDF section 13): shown on /faq (with FAQPage structured data) and
 * the first few on the homepage. Admins edit (FaqPolicy); changes are live
 * at once. Drag rows to reorder.
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?string $modelLabel = 'question';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'question';

    /**
     * @param  Schema  $schema
     * @return Schema
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('question')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('answer')
                    ->required()
                    ->maxLength(3000)
                    ->rows(8)
                    ->helperText('Plain text. Leave a blank line between paragraphs.')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Show on the website')
                    ->default(true),
            ]);
    }

    /**
     * @param  Table  $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order', fn (): bool => Gate::allows('reorder', Faq::class))
            ->paginated(false)
            ->columns([
                TextColumn::make('question')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Faq $record): string => str($record->answer)->squish()->limit(120)->toString()),
                ToggleColumn::make('is_active')
                    ->label('Shown')
                    ->disabled(fn (Faq $record): bool => ! Gate::allows('update', $record)),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Shown on the website'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ManageFaqs::route('/'),
        ];
    }
}
