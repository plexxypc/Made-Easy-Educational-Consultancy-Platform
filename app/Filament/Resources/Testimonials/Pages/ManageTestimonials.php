<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTestimonials extends ManageRecords
{
    protected static string $resource = TestimonialResource::class;

    /**
     * "Add testimonial": for one received another way (e.g. on WhatsApp).
     * It starts as pending like any other, so it is still approved explicitly.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add testimonial'),
        ];
    }
}
