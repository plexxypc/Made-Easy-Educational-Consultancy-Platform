<?php

namespace Tests\Feature\Updates;

use App\Actions\Posts\SavePost;
use App\Actions\Posts\SetPostPublishDate;
use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Back-office post editor: staff and admins write, only admins publish,
 * customers and guests never get in.
 */
class PostAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var PostCategory
     */
    private PostCategory $category;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->category = PostCategory::factory()->create(['name' => 'JAMB News']);
    }

    /**
     * Customers get a 403 and guests the login page, for every editor URL.
     *
     * @return void
     */
    public function test_customers_and_guests_cannot_reach_the_post_editor(): void
    {
        $post = Post::factory()->create();
        $urls = [
            PostResource::getUrl('index'),
            PostResource::getUrl('create'),
            PostResource::getUrl('edit', ['record' => $post]),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->actingAs(User::factory()->create());

        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->actingAs(User::factory()->staff()->create());
        $this->get(PostResource::getUrl('index'))->assertOk();
        $this->get(PostResource::getUrl('create'))->assertOk();
    }

    /**
     * Staff write drafts: they can't publish or schedule, even by sending a
     * publish date, and can't change a post once it is live or scheduled.
     *
     * @return void
     */
    public function test_staff_write_drafts_but_cannot_publish(): void
    {
        $staff_user = User::factory()->staff()->create();
        $this->actingAs($staff_user);

        Livewire::test(CreatePost::class)
            ->assertFormFieldIsDisabled('published_at')
            ->fillForm([
                'title' => 'JAMB Mock Exam Announced',
                'post_category_id' => $this->category->id,
                'body' => '<p>The mock exam holds next month.</p>',
            ])
            ->assertSchemaStateSet(['slug' => 'jamb-mock-exam-announced'])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->sole();
        $this->assertSame(PostStatus::Draft, $post->status());
        $this->assertTrue($post->author->is($staff_user));
        $this->assertStringContainsString('The mock exam holds next month.', $post->body);

        // The server ignores a publish date from staff, whatever arrives.
        $data = app(SavePost::class)->for_create(['title' => 'X', 'published_at' => now()], $staff_user);
        $this->assertNull($data['published_at']);
        $data = app(SavePost::class)->for_update(['published_at' => now(), 'author_id' => 999], $post, $staff_user);
        $this->assertNull($data['published_at']);
        $this->assertArrayNotHasKey('author_id', $data);

        $this->assertThrows(fn () => app(SetPostPublishDate::class)->handle($post, now(), $staff_user), AuthorizationException::class);
        $this->assertNull($post->refresh()->published_at);

        // Drafts stay editable; live and scheduled posts don't.
        $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertOk();
        $post->forceFill(['published_at' => now()->addDay()])->save();
        $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertForbidden();
        $post->forceFill(['published_at' => now()->subDay()])->save();
        $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertForbidden();
        Livewire::test(ListPosts::class)
            ->assertActionHidden(TestAction::make('publish_now')->table($post))
            ->assertActionHidden(TestAction::make('unpublish')->table($post))
            ->assertActionHidden(TestAction::make('edit')->table($post))
            ->assertActionHidden(TestAction::make('delete')->table($post));
    }

    /**
     * Admins schedule posts with the date picker and publish or unpublish
     * from the list; the list shows each post's status.
     *
     * @return void
     */
    public function test_admin_schedules_publishes_and_sees_status(): void
    {
        $admin_user = User::factory()->admin()->create();
        $this->actingAs($admin_user);
        $go_live = now()->addDays(2)->startOfMinute();

        Livewire::test(CreatePost::class)
            ->assertFormFieldIsEnabled('published_at')
            ->fillForm([
                'title' => 'Post-UTME Forms On Sale',
                'post_category_id' => $this->category->id,
                'body' => '<p>Forms are now on sale.</p>',
                // Typed in the blog timezone (Lagos, UTC+1), stored in UTC.
                'published_at' => $go_live->copy()->timezone('Africa/Lagos')->format('Y-m-d H:i:s'),
                'meta_title' => 'Post-UTME Forms 2027',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $scheduled = Post::query()->where('slug', 'post-utme-forms-on-sale')->sole();
        $this->assertTrue($scheduled->published_at->equalTo($go_live));
        $this->assertSame(PostStatus::Scheduled, $scheduled->status());

        $draft = Post::factory()->create(['title' => 'Unfinished Draft']);
        $live = Post::factory()->published()->create(['title' => 'Already Live']);

        Livewire::test(ListPosts::class)
            ->assertCanSeeTableRecords([$scheduled, $draft, $live])
            ->assertTableColumnStateSet('status', PostStatus::Scheduled, $scheduled)
            ->assertTableColumnStateSet('status', PostStatus::Draft, $draft)
            ->assertTableColumnStateSet('status', PostStatus::Published, $live)
            ->filterTable('status', PostStatus::Scheduled->value)
            ->assertCanSeeTableRecords([$scheduled])
            ->assertCanNotSeeTableRecords([$draft, $live]);

        Livewire::test(ListPosts::class)
            ->callAction(TestAction::make('publish_now')->table($draft))
            ->assertNotified('Post published')
            ->callAction(TestAction::make('unpublish')->table($live))
            ->assertNotified('Post moved back to drafts');

        $this->assertSame(PostStatus::Published, $draft->refresh()->status());
        $this->assertSame(PostStatus::Draft, $live->refresh()->status());

        // Admins can still edit a published post, including clearing its date.
        Livewire::test(EditPost::class, ['record' => $draft->getRouteKey()])
            ->fillForm(['published_at' => null])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($draft->refresh()->published_at);
    }

    /**
     * The featured image goes to the public disk and shows on the site; SVG
     * and non-image files are refused; replaced or orphaned images are
     * deleted.
     *
     * @return void
     */
    public function test_featured_image_is_stored_on_the_public_disk(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([
            UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            UploadedFile::fake()->create('flyer.pdf', 40, 'application/pdf'),
        ] as $refused_file) {
            Livewire::test(CreatePost::class)
                ->fillForm([
                    'title' => 'Scholarship Window Opens',
                    'post_category_id' => $this->category->id,
                    'body' => '<p>Apply now.</p>',
                    'featured_image_path' => $refused_file,
                ])
                ->call('create')
                ->assertHasFormErrors(['featured_image_path']);
        }
        $this->assertDatabaseCount('posts', 0);

        Livewire::test(CreatePost::class)
            ->fillForm([
                'title' => 'Scholarship Window Opens',
                'post_category_id' => $this->category->id,
                'body' => '<p>Apply now.</p>',
                'featured_image_path' => $this->png('cover.png'),
                'featured_image_alt_text' => 'Scholarship application form',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->sole();
        $first_path = $post->featured_image_path;
        $this->assertStringStartsWith(Post::IMAGE_DIRECTORY.'/', $first_path);
        Storage::disk('public')->assertExists($first_path);
        Storage::disk('local')->assertMissing($first_path);
        // The site shows the resized copy (FeaturedImageResizeTest); the original is kept.
        $first_display_path = $post->featured_image_display_path;
        $this->assertNotNull($first_display_path);
        $this->assertSame(Storage::disk('public')->url($first_display_path), $post->featuredImageUrl());

        $post->forceFill(['published_at' => now()->subMinute()])->save();
        $this->get($post->publicUrl())->assertOk()->assertSee($post->featuredImageUrl(), false);
        $this->get(route('updates'))->assertSee($post->featuredImageUrl(), false);

        // Replacing the image deletes the old file and its copy. This one isn't a
        // readable image, so no copy is made and the original is shown instead.
        $new_path = Post::IMAGE_DIRECTORY.'/'.Str::random(40).'.png';
        Storage::disk('public')->put($new_path, 'png');
        $post->update(['featured_image_path' => $new_path]);
        Storage::disk('public')->assertMissing($first_path);
        Storage::disk('public')->assertMissing($first_display_path);
        Storage::disk('public')->assertExists($new_path);
        $this->assertNull($post->refresh()->featured_image_display_path);
        $this->assertSame(Storage::disk('public')->url($new_path), $post->featuredImageUrl());

        $post->delete();
        Storage::disk('public')->assertMissing($new_path);
    }

    /**
     * The slug follows the title until edited, and must be unique.
     *
     * @return void
     */
    public function test_slug_is_generated_editable_and_unique(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Post::factory()->create(['slug' => 'waec-timetable']);

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'WAEC Timetable'])
            ->assertSchemaStateSet(['slug' => 'waec-timetable'])
            ->fillForm(['post_category_id' => $this->category->id, 'body' => '<p>Out now.</p>'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        Livewire::test(CreatePost::class)
            ->fillForm(['title' => 'WAEC Timetable'])
            ->fillForm(['slug' => 'waec-2027-timetable', 'post_category_id' => $this->category->id, 'body' => '<p>Out now.</p>'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('posts', ['slug' => 'waec-2027-timetable', 'title' => 'WAEC Timetable']);
    }

    /**
     * A real 1x1 PNG, built without GD so the upload rules are tested on their own.
     *
     * @param  string  $name
     * @return UploadedFile
     */
    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        );
    }
}
