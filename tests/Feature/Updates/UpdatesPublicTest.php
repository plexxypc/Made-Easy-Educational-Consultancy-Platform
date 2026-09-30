<?php

namespace Tests\Feature\Updates;

use App\Models\Post;
use App\Models\PostCategory;
use Database\Seeders\PostCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public Updates pages: only posts whose publish time has passed are ever
 * listed or reachable; category filtering; SEO tags; safe body HTML.
 */
class UpdatesPublicTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The PDF section 10 categories are seeded, once.
     *
     * @return void
     */
    public function test_categories_are_seeded_from_the_requirements(): void
    {
        $this->seed(PostCategorySeeder::class);
        $this->seed(PostCategorySeeder::class);

        $this->assertSame(12, PostCategory::query()->count());
        $this->assertDatabaseHas('post_categories', ['name' => 'NIN/JAMB Information', 'slug' => 'nin-jamb-information']);
        $this->assertDatabaseHas('post_categories', ['name' => 'Post-UTME Announcements', 'slug' => 'post-utme-announcements']);
        $this->assertDatabaseHas('post_categories', ['name' => 'Cut-Off Mark Information', 'slug' => 'cut-off-mark-information']);
    }

    /**
     * Drafts and scheduled posts are never listed or reachable, anywhere;
     * a scheduled post goes live by itself once its time passes.
     *
     * @return void
     */
    public function test_draft_and_scheduled_posts_are_never_public(): void
    {
        $published = Post::factory()->published()->create(['title' => 'Published Post Title', 'slug' => 'published-post']);
        $draft = Post::factory()->create(['title' => 'Draft Post Title', 'slug' => 'draft-post']);
        $scheduled = Post::factory()->scheduled(now()->addHours(3))->create(['title' => 'Scheduled Post Title', 'slug' => 'scheduled-post']);

        foreach ([route('updates'), route('home'), $draft->category->publicUrl(), $scheduled->category->publicUrl()] as $url) {
            $this->get($url)->assertOk()->assertDontSee('Draft Post Title')->assertDontSee('Scheduled Post Title');
        }

        $this->get(route('updates'))->assertSee('Published Post Title');
        $this->get(route('home'))->assertSee('Latest Updates')->assertSee('Published Post Title');
        $this->get($published->publicUrl())->assertOk();
        $this->get('/updates/draft-post')->assertNotFound();
        $this->get('/updates/scheduled-post')->assertNotFound();
        $this->get('/updates/'.$draft->id)->assertNotFound();

        $this->travel(3)->hours();
        $this->travel(1)->minutes();

        $this->get('/updates/scheduled-post')->assertOk()->assertSee('Scheduled Post Title');
        $this->get(route('updates'))->assertSee('Scheduled Post Title')->assertDontSee('Draft Post Title');

        // Unpublishing (back to draft) takes it down again.
        $scheduled->forceFill(['published_at' => null])->save();
        $this->get('/updates/scheduled-post')->assertNotFound();
    }

    /**
     * A category page lists only that category's published posts.
     *
     * @return void
     */
    public function test_category_filter_only_returns_that_category(): void
    {
        $jamb = PostCategory::factory()->create(['name' => 'JAMB News', 'slug' => 'jamb-news']);
        $waec = PostCategory::factory()->create(['name' => 'WAEC Updates', 'slug' => 'waec-updates']);
        Post::factory()->published()->for($jamb, 'category')->create(['title' => 'JAMB Mock Exam Date']);
        Post::factory()->published()->for($jamb, 'category')->create(['title' => 'JAMB Result Release']);
        Post::factory()->published()->for($waec, 'category')->create(['title' => 'WAEC Timetable Out']);
        Post::factory()->for($jamb, 'category')->create(['title' => 'JAMB Draft Notice']);

        $this->get(route('updates.category', $jamb))
            ->assertOk()
            ->assertSee('<h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">JAMB News</h1>', false)
            ->assertSee('JAMB Mock Exam Date')
            ->assertSee('JAMB Result Release')
            ->assertDontSee('WAEC Timetable Out')
            ->assertDontSee('JAMB Draft Notice');

        $this->get('/updates/category/waec-updates')
            ->assertOk()
            ->assertSee('WAEC Timetable Out')
            ->assertDontSee('JAMB Mock Exam Date');

        $this->get('/updates/category/no-such-category')->assertNotFound();

        $this->get(route('updates'))
            ->assertSee('JAMB Mock Exam Date')
            ->assertSee('WAEC Timetable Out')
            ->assertSee('WAEC Updates');
    }

    /**
     * Newest first, paginated; the homepage shows the newest three.
     *
     * @return void
     */
    public function test_index_is_newest_first_and_paginated(): void
    {
        config(['madeeasy.blog.per_page' => 2]);
        Post::factory()->published(now()->subDays(4))->create(['title' => 'Oldest Update']);
        Post::factory()->published(now()->subDays(3))->create(['title' => 'Older Update']);
        Post::factory()->published(now()->subDays(2))->create(['title' => 'Newer Update']);
        Post::factory()->published(now()->subDay())->create(['title' => 'Newest Update']);

        $this->get(route('updates'))
            ->assertSeeInOrder(['Newest Update', 'Newer Update'])
            ->assertDontSee('Older Update');
        $this->get(route('updates', ['page' => 2]))->assertSeeInOrder(['Older Update', 'Oldest Update']);

        $this->get(route('home'))
            ->assertSeeInOrder(['Latest Updates', 'Newest Update', 'Newer Update', 'Older Update'])
            ->assertDontSee('Oldest Update');
    }

    /**
     * The homepage hides "Latest Updates" until something is published.
     *
     * @return void
     */
    public function test_homepage_hides_latest_updates_when_there_are_none(): void
    {
        Post::factory()->scheduled()->create();

        $this->get(route('home'))->assertOk()->assertDontSee('Latest Updates');
        $this->get(route('updates'))->assertOk()->assertSee('No updates have been published yet.');
    }

    /**
     * The post page uses the meta title / description as real tags, with
     * sensible fallbacks, and a canonical URL.
     *
     * @return void
     */
    public function test_post_page_renders_meta_tags(): void
    {
        $post = Post::factory()->published()->create([
            'title' => 'JAMB 2027 Registration Opens',
            'slug' => 'jamb-2027-registration',
            'meta_title' => 'JAMB 2027 Registration: Dates & Fees',
            'meta_description' => 'Everything you need for JAMB 2027 registration.',
        ]);

        $this->get('/updates/jamb-2027-registration')
            ->assertOk()
            ->assertSee('<title>JAMB 2027 Registration: Dates &amp; Fees</title>', false)
            ->assertSee('<meta name="description" content="Everything you need for JAMB 2027 registration.">', false)
            ->assertSee('<link rel="canonical" href="'.$post->publicUrl().'">', false)
            ->assertSee('<h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">JAMB 2027 Registration Opens</h1>', false);

        Post::factory()->published()->create([
            'title' => 'NECO Results Out',
            'slug' => 'neco-results-out',
            'excerpt' => 'Check your NECO result online today.',
            'meta_title' => null,
            'meta_description' => null,
        ]);

        $this->get('/updates/neco-results-out')
            ->assertSee('<title>NECO Results Out</title>', false)
            ->assertSee('<meta name="description" content="Check your NECO result online today.">', false);
    }

    /**
     * Body HTML is sanitised: formatting stays, scripts and handlers go.
     *
     * @return void
     */
    public function test_post_body_is_sanitised(): void
    {
        Post::factory()->published()->create([
            'slug' => 'safe-post',
            'body' => '<h2>Key dates</h2><p>Register <strong>early</strong>.</p><script>alert("x")</script><p><a href="javascript:alert(1)">bad link</a></p><img src="https://example.com/x.png" onerror="alert(2)">',
        ]);

        $response = $this->get('/updates/safe-post')->assertOk();
        $body = $response->getContent();

        $response->assertSee('<h2>Key dates</h2>', false)->assertSee('<strong>early</strong>', false);
        $this->assertStringNotContainsString('alert("x")', $body);
        $this->assertStringNotContainsString('javascript:alert', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    /**
     * The Updates nav item now goes to the real index.
     *
     * @return void
     */
    public function test_nav_links_to_the_updates_index(): void
    {
        $this->get(route('updates'))
            ->assertOk()
            ->assertSee('Admission Updates')
            ->assertSee('href="'.route('updates').'"', false);
    }
}
