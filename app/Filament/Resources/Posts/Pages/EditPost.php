<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Actions\Posts\SavePost;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    /**
     * Slug before this save, to notice a changed address on a live post.
     */
    protected ?string $slug_before_save = null;

    /**
     * @return void
     */
    protected function beforeSave(): void
    {
        $this->slug_before_save = $this->getRecord()->getOriginal('slug');
    }

    /**
     * After the address of a live post changed, say plainly which link died.
     *
     * @return void
     */
    protected function afterSave(): void
    {
        /** @var Post $post */
        $post = $this->getRecord();

        if ($post->isPublished() && $this->slug_before_save !== null && $this->slug_before_save !== $post->slug) {
            Notification::make()
                ->warning()
                ->title('Post address changed')
                ->body("Links to /updates/{$this->slug_before_save} now show \"page not found\". Update anywhere you shared the old link.")
                ->persistent()
                ->send();
        }
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_live')
                ->label('View on site')
                ->color('gray')
                ->url(fn (Post $record): string => $record->publicUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Post $record): bool => $record->isPublished()),
            Action::make('preview')
                ->label('Preview')
                ->color('gray')
                ->url(fn (Post $record): string => $record->previewUrl(), shouldOpenInNewTab: true)
                ->visible(fn (Post $record): bool => ! $record->isPublished()),
            DeleteAction::make(),
        ];
    }

    /**
     * Keeps the author; keeps the publish date unless the user may publish.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Post $post */
        $post = $this->getRecord();

        return app(SavePost::class)->for_update($data, $post, auth()->user());
    }
}
