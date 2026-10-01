<?php

namespace Tests\Feature\Updates;

use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Draft/scheduled post previews (signed link + staff/admin sign-in) and the
 * featured image alt text.
 */
class PostPreviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Staff and admins open the preview of a draft or scheduled post; the
     * page is marked noindex, has no canonical link and says it's a preview.
     * The post stays 404 at its public address.
     *
     * @return void
     */
    public function test_staff_and_admins_can_preview_unpublished_posts(): void
    {
        $draft = Post::factory()->create(['title' => 'Draft Post Title', 'slug' => 'draft-post']);
        $scheduled = Post::factory()->scheduled(now()->addDays(2))->create(['title' => 'Scheduled Post Title']);

        foreach ([User::factory()->staff()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->get($draft->previewUrl())
                ->assertOk()
                ->assertSee('Draft Post Title')
                ->assertSee('Preview only. This post is a draft')
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
                ->assertDontSee('rel="canonical"', false);

            $this->get($scheduled->previewUrl())
                ->assertOk()
                ->assertSee('Scheduled Post Title')
                ->assertSee('This post is scheduled and goes live on');
        }

        $this->get($draft->publicUrl())->assertNotFound();
        $this->get(route('updates'))->assertDontSee('Draft Post Title');
    }

    /**
     * Guests are sent to log in, customers get 403, and a missing, tampered
     * or expired signature is refused even for staff.
     *
     * @return void
     */
    public function test_preview_needs_a_valid_signature_and_a_staff_sign_in(): void
    {
        $draft = Post::factory()->create(['title' => 'Secret Draft']);
        $preview_url = $draft->previewUrl();

        $this->get($preview_url)->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get($preview_url)
            ->assertForbidden();

        $this->actingAs(User::factory()->staff()->create());
        $this->get(route('updates.preview', ['post' => $draft]))->assertForbidden();
        $this->get(str_replace('signature=', 'signature=0', $preview_url))->assertForbidden();

        $other_draft = Post::factory()->create();
        $this->get(str_replace('/preview/'.$draft->id.'?', '/preview/'.$other_draft->id.'?', $preview_url))->assertForbidden();

        $this->travel((int) config('madeeasy.blog.preview_link_minutes') + 1)->minutes();
        $this->get($preview_url)->assertForbidden();
        $this->get($draft->previewUrl())->assertOk()->assertSee('Secret Draft');
    }

    /**
     * Once live, the preview link redirects to the public page; the admin
     * screens show "Preview" only for unpublished posts.
     *
     * @return void
     */
    public function test_published_posts_redirect_and_preview_action_visibility(): void
    {
        $published = Post::factory()->published()->create();
        $draft = Post::factory()->create();
        $this->actingAs(User::factory()->staff()->create());

        $this->get($published->previewUrl())->assertRedirect($published->publicUrl());

        Livewire::test(ListPosts::class)
            ->assertActionVisible(TestAction::make('preview')->table($draft))
            ->assertActionHidden(TestAction::make('preview')->table($published));

        Livewire::test(EditPost::class, ['record' => $draft->getRouteKey()])
            ->assertActionVisible('preview');
        $this->assertTrue(URL::hasValidSignature(request()->create($draft->previewUrl())));
    }

    /**
     * The alt text is required with a featured image, saved, and used as the
     * image's alt on the public post page.
     *
     * @return void
     */
    public function test_featured_image_alt_text_is_required_and_rendered(): void
    {
        Storage::fake('public');
        $category = PostCategory::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $png = fn (): UploadedFile => UploadedFile::fake()->createWithContent(
            'cover.png',
            (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='),
        );
        $form = [
            'title' => 'UTME Results Are Out',
            'post_category_id' => $category->id,
            'body' => '<p>Check your result.</p>',
            'published_at' => now()->subHour()->timezone((string) config('madeeasy.blog.timezone'))->format('Y-m-d H:i'),
        ];

        Livewire::test(CreatePost::class)
            ->fillForm($form + ['featured_image_path' => $png()])
            ->call('create')
            ->assertHasFormErrors(['featured_image_alt_text' => 'required']);

        Livewire::test(CreatePost::class)
            ->fillForm($form)
            ->call('create')
            ->assertHasNoFormErrors();
        Post::query()->sole()->delete();

        Livewire::test(CreatePost::class)
            ->fillForm($form + ['featured_image_path' => $png(), 'featured_image_alt_text' => 'Students checking UTME results on a phone'])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = Post::query()->sole();
        $this->assertSame('Students checking UTME results on a phone', $post->featured_image_alt_text);

        $this->get($post->publicUrl())
            ->assertOk()
            ->assertSee('<img src="'.$post->featuredImageUrl().'" alt="Students checking UTME results on a phone"', false);
    }
}
