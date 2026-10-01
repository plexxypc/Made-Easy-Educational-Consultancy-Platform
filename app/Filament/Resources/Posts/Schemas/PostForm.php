<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Support\SlugField;
use App\Models\Post;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class PostForm
{
    /**
     * Post editor: content on the left; image, publishing and SEO on the right.
     *
     * The publish date is only editable by users allowed to publish (admins);
     * for staff it is disabled and SavePost ignores it anyway. Images are
     * public marketing images (public disk), limited to JPEG/PNG/WebP and
     * checked from the file contents.
     *
     * @param  Schema  $schema
     * @return Schema
     */
    public static function configure(Schema $schema): Schema
    {
        $can_publish = Gate::allows('publish', Post::class);
        $timezone = (string) config('madeeasy.blog.timezone');
        $image_types = config('madeeasy.blog.image_mime_types');
        $image_max_kilobytes = (int) config('madeeasy.blog.image_max_kilobytes');

        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->schema([
                        Section::make()
                            ->columns(2)
                            ->schema([
                                SlugField::name_input('title', slug_is_locked: fn (?Model $record): bool => $record instanceof Post && $record->isPublished())
                                    ->columnSpanFull(),
                                SlugField::slug_input()
                                    ->live(onBlur: true)
                                    ->hint(fn (?Model $record, ?string $state): ?string => self::slug_change_warning($record, $state))
                                    ->hintIcon(Heroicon::ExclamationTriangle)
                                    ->hintColor('danger')
                                    ->helperText(fn (?Model $record): string => $record instanceof Post && $record->isPublished()
                                        ? 'This post is live, so the address no longer follows the title. There are no redirects: changing it breaks every existing link and shared post.'
                                        : 'The post address: /updates/your-slug. Filled in from the title until the post is published.'),
                                Select::make('post_category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                                Textarea::make('excerpt')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->helperText('Short summary for the updates list. Leave empty to use the start of the post.')
                                    ->columnSpanFull(),
                                RichEditor::make('body')
                                    ->required()
                                    ->toolbarButtons([
                                        ['bold', 'italic', 'underline', 'strike', 'link'],
                                        ['h2', 'h3'],
                                        ['blockquote', 'bulletList', 'orderedList', 'table'],
                                        ['attachFiles'],
                                        ['undo', 'redo'],
                                    ])
                                    ->fileAttachmentsDisk('public')
                                    ->fileAttachmentsVisibility('public')
                                    ->fileAttachmentsDirectory(Post::ATTACHMENT_DIRECTORY)
                                    ->fileAttachmentsAcceptedFileTypes($image_types)
                                    ->fileAttachmentsMaxSize($image_max_kilobytes)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->schema([
                        Section::make('Publishing')
                            ->schema([
                                DateTimePicker::make('published_at')
                                    ->label('Publish at')
                                    ->seconds(false)
                                    ->timezone($timezone)
                                    ->disabled(! $can_publish)
                                    ->helperText($can_publish
                                        ? "Empty = draft. A future time schedules it; it goes live by itself then. Times are in {$timezone}."
                                        : 'Only an admin can publish. Save as a draft and ask an admin to review and publish it.'),
                            ]),
                        Section::make('Featured image')
                            ->schema([
                                FileUpload::make('featured_image_path')
                                    ->hiddenLabel()
                                    ->image()
                                    ->disk('public')
                                    ->visibility('public')
                                    ->directory(Post::IMAGE_DIRECTORY)
                                    ->acceptedFileTypes($image_types)
                                    ->maxSize($image_max_kilobytes)
                                    ->helperText('JPEG, PNG or WebP, up to '.intdiv($image_max_kilobytes, 1024).' MB. Shown on the updates list and at the top of the post.'),
                                TextInput::make('featured_image_alt_text')
                                    ->label('Image description (alt text)')
                                    ->maxLength(150)
                                    ->required(fn (Get $get): bool => filled($get('featured_image_path')))
                                    ->helperText('Required with an image. Describe what it shows for people using screen readers, e.g. "Students writing the UTME in a CBT centre".'),
                            ]),
                        Section::make('Search engines')
                            ->description('Optional. Defaults to the title and summary.')
                            ->schema([
                                TextInput::make('meta_title')
                                    ->label('Meta title')
                                    ->maxLength(70)
                                    ->helperText('Up to 70 characters, used as the page title as written.'),
                                Textarea::make('meta_description')
                                    ->label('Meta description')
                                    ->rows(3)
                                    ->maxLength(160),
                            ]),
                    ]),
            ]);
    }

    /**
     * Shown as soon as the slug of a published post is edited. There are no
     * redirects, so the old address will 404 after saving.
     *
     * @param  Model|null  $record
     * @param  string|null  $state  Slug currently in the form.
     * @return string|null
     */
    public static function slug_change_warning(?Model $record, ?string $state): ?string
    {
        if (! $record instanceof Post || ! $record->isPublished() || $state === $record->slug) {
            return null;
        }

        return "Published post: saving breaks links to /updates/{$record->slug}";
    }
}
