<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Actions\Posts\SetPostPublishDate;
use App\Enums\PostStatus;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class PostsTable
{
    /**
     * Post list with a clear Published / Scheduled / Draft badge. Drafts and
     * scheduled posts sort first, then newest published.
     *
     * @param  Table  $table
     * @return Table
     */
    public static function configure(Table $table): Table
    {
        $timezone = (string) config('madeeasy.blog.timezone');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'author']))
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('case when published_at is null then 0 when published_at > ? then 1 else 2 end', [now()])
                ->orderByDesc('published_at')
                ->orderByDesc('updated_at'))
            ->columns([
                ImageColumn::make('featured_image_path')
                    ->label('')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->imageSize(48),
                TextColumn::make('title')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Post $record): string => '/updates/'.$record->slug),
                TextColumn::make('status')
                    ->state(fn (Post $record): PostStatus => $record->status())
                    ->badge(),
                TextColumn::make('published_at')
                    ->label('Publish date')
                    ->dateTime('j M Y, g:ia', $timezone)
                    ->placeholder('Not set')
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(PostStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        PostStatus::Draft->value => $query->whereNull('published_at'),
                        PostStatus::Scheduled->value => $query->where('published_at', '>', now()),
                        PostStatus::Published->value => $query->published(),
                        default => $query,
                    }),
                SelectFilter::make('post_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('view_live')
                    ->label('View')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Post $record): string => $record->publicUrl(), shouldOpenInNewTab: true)
                    ->visible(fn (Post $record): bool => $record->isPublished()),
                Action::make('publish_now')
                    ->label('Publish now')
                    ->icon(Heroicon::OutlinedRocketLaunch)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('The post goes live on the Updates page straight away.')
                    ->visible(fn (Post $record): bool => ! $record->isPublished() && Gate::allows('publish', Post::class))
                    ->action(function (Post $record, SetPostPublishDate $set_post_publish_date): void {
                        $set_post_publish_date->handle($record, now(), auth()->user());
                        Notification::make()->success()->title('Post published')->send();
                    }),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon(Heroicon::OutlinedEyeSlash)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('The post becomes a draft again and its page stops working until it is republished.')
                    ->visible(fn (Post $record): bool => $record->published_at !== null && Gate::allows('publish', Post::class))
                    ->action(function (Post $record, SetPostPublishDate $set_post_publish_date): void {
                        $set_post_publish_date->handle($record, null, auth()->user());
                        Notification::make()->success()->title('Post moved back to drafts')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
