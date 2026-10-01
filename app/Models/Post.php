<?php

namespace App\Models;

use App\Actions\Posts\MakeFeaturedImageDisplayCopy;
use App\Enums\PostStatus;
use Database\Factories\PostFactory;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Updates (blog) post: news for students and parents (PDF section 10).
 *
 * Visibility: a post is public only once `published_at` has passed. NULL
 * is a draft and a future time is scheduled; both 404 on the public site.
 * Who may publish is decided by PostPolicy::publish (admins only).
 *
 * Images: the featured image and images inserted in the body are marketing
 * content on the PUBLIC disk (served directly at /storage/...), never the
 * private disk used for customer documents.
 *
 * @property int $id
 * @property int $post_category_id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body Rich text HTML; render with body_html().
 * @property string|null $featured_image_path Original upload, path on the public disk.
 * @property string|null $featured_image_display_path Resized copy the site shows; see MakeFeaturedImageDisplayCopy.
 * @property string|null $featured_image_alt_text
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon|null $published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'post_category_id', 'author_id', 'title', 'slug', 'excerpt', 'body', 'featured_image_path',
    'featured_image_alt_text', 'meta_title', 'meta_description', 'published_at',
])]
class Post extends Model implements HasRichContent
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, InteractsWithRichContent;

    /**
     * Folder on the public disk for featured images.
     *
     * @var string
     */
    public const IMAGE_DIRECTORY = 'posts/featured';

    /**
     * Folder on the public disk for images inserted in the body.
     *
     * @var string
     */
    public const ATTACHMENT_DIRECTORY = 'posts/attachments';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * A replaced or removed featured image is deleted from the public disk,
     * and the resized display copy follows the original
     * (MakeFeaturedImageDisplayCopy). Display copies are made synchronously
     * on save: one image of at most 2 MB, so a queue isn't worth it.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::updated(function (Post $post): void {
            $old_path = $post->getOriginal('featured_image_path');

            if ($post->wasChanged('featured_image_path') && filled($old_path)) {
                Storage::disk('public')->delete($old_path);
            }
        });

        static::saved(function (Post $post): void {
            if ($post->needsDisplayImage()) {
                app(MakeFeaturedImageDisplayCopy::class)->handle($post);
            }
        });

        static::deleted(function (Post $post): void {
            Storage::disk('public')->delete(array_filter([$post->featured_image_path, $post->featured_image_display_path]));
        });
    }

    /**
     * Whether the display copy is missing or belongs to an older image.
     * A post whose copy couldn't be made (NULL) is retried on every save.
     *
     * @return bool
     */
    public function needsDisplayImage(): bool
    {
        $expected = filled($this->featured_image_path) ? MakeFeaturedImageDisplayCopy::path_for($this->featured_image_path) : null;

        return $this->featured_image_display_path !== $expected;
    }

    /**
     * The body editor's image uploads go to the public disk.
     *
     * @return void
     */
    protected function setUpRichContent(): void
    {
        $this->registerRichContent('body')
            ->fileAttachmentsDisk('public')
            ->fileAttachmentsVisibility('public');
    }

    /**
     * @return BelongsTo<PostCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'post_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Posts visible on the public site: publish time reached.
     *
     * @param  Builder<Post>  $query
     * @return void
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Newest first (publish time, then id for ties).
     *
     * @param  Builder<Post>  $query
     * @return void
     */
    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    /**
     * @return PostStatus
     */
    public function status(): PostStatus
    {
        return match (true) {
            $this->published_at === null => PostStatus::Draft,
            $this->published_at->isFuture() => PostStatus::Scheduled,
            default => PostStatus::Published,
        };
    }

    /**
     * Whether the public can see it now.
     *
     * @return bool
     */
    public function isPublished(): bool
    {
        return $this->status() === PostStatus::Published;
    }

    /**
     * Sanitised body HTML for the public page (Filament's renderer strips
     * scripts, event handlers and unsafe URLs).
     *
     * @return string
     */
    public function bodyHtml(): string
    {
        return $this->renderRichContent('body');
    }

    /**
     * The excerpt, or the start of the body as plain text.
     *
     * @param  int  $limit
     * @return string
     */
    public function summary(int $limit = 200): string
    {
        $text = filled($this->excerpt) ? $this->excerpt : strip_tags(str_replace('<', ' <', $this->body));

        return Str::of($text)->squish()->limit($limit)->toString();
    }

    /**
     * <title> for the post page: the meta title as written, else the title.
     *
     * @return string
     */
    public function seoTitle(): string
    {
        return filled($this->meta_title) ? $this->meta_title : $this->title;
    }

    /**
     * <meta name="description">: the meta description, else the summary.
     *
     * @return string
     */
    public function seoDescription(): string
    {
        return filled($this->meta_description) ? $this->meta_description : $this->summary(160);
    }

    /**
     * Publish date for readers, in the site timezone, e.g. "3 Oct 2026".
     *
     * @return string
     */
    public function publishedOnLabel(): string
    {
        return (string) $this->published_at?->timezone((string) config('madeeasy.blog.timezone'))->format('j M Y');
    }

    /**
     * Public URL of the featured image: the resized copy when there is one,
     * else the original upload.
     *
     * @return string|null
     */
    public function featuredImageUrl(): ?string
    {
        $path = $this->featured_image_display_path ?? $this->featured_image_path;

        return filled($path) ? Storage::disk('public')->url($path) : null;
    }

    /**
     * @return string
     */
    public function publicUrl(): string
    {
        return route('updates.show', ['slug' => $this->slug]);
    }

    /**
     * Expiring signed link to see a draft or scheduled post as it will look
     * on the site. Opening it still needs a staff/admin sign-in
     * (UpdatesController::preview), so a leaked link shows nothing.
     *
     * @return string
     */
    public function previewUrl(): string
    {
        return URL::temporarySignedRoute(
            'updates.preview',
            now()->addMinutes((int) config('madeeasy.blog.preview_link_minutes')),
            ['post' => $this->id],
        );
    }
}
