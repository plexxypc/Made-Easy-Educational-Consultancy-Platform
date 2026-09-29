<?php

namespace App\Filament\Resources\ServiceCategories\Schemas;

use App\Filament\Support\SlugField;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceCategoryForm
{
    /**
     * Category form.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        SlugField::name_input(),
                        SlugField::slug_input(),
                        TextInput::make('sort_order')
                            ->label('Sort order')
                            ->helperText('Lower numbers are shown first.')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->default(0),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
