<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\Faqs\FaqResource;
use App\Models\Faq;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFaqs extends ManageRecords
{
    protected static string $resource = FaqResource::class;

    /**
     * New questions go to the end of the list.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateDataUsing(fn (array $data): array => [...$data, 'sort_order' => (int) Faq::query()->max('sort_order') + 1]),
        ];
    }
}
