<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Actions\Posts\SavePost;
use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Sets the author; drops the publish date unless the user may publish.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(SavePost::class)->for_create($data, auth()->user());
    }
}
