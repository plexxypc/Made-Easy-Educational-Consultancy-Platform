<?php

namespace Tests\Feature\Website;

use App\Models\Post;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\PublicNavigation;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * sitemap.xml / robots.txt, meta and Open Graph tags, and the WhatsApp
 * click-to-chat button.
 */
class SeoAndWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Active services and published posts are listed; inactive services,
     * drafts and scheduled posts are not.
     *
     * @return void
     */
    public function test_sitemap_lists_only_public_content(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => 'exam-registration']);
        $active = Service::factory()->for($category, 'category')->create(['slug' => 'waec-registration', 'is_active' => true]);
        $inactive = Service::factory()->for($category, 'category')->create(['slug' => 'nabteb-registration', 'is_active' => false]);
        $empty_category = ServiceCategory::factory()->create(['slug' => 'coming-soon']);
        Service::factory()->for($empty_category, 'category')->create(['is_active' => false]);

        $published = Post::factory()->create(['slug' => 'utme-dates-out', 'published_at' => now()->subDay()]);
        $draft = Post::factory()->create(['slug' => 'draft-post', 'published_at' => null]);
        $scheduled = Post::factory()->create(['slug' => 'scheduled-post', 'published_at' => now()->addDay()]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = (string) $response->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap.xml must be valid XML');
        foreach ([route('home'), route('faq'), route('maths-tutoring'), $category->publicUrl(), route('services.show', ['category' => $category, 'service' => $active]), $published->publicUrl()] as $url) {
            $this->assertStringContainsString('<loc>'.$url.'</loc>', $xml);
        }
        foreach ([route('services.show', ['category' => $category, 'service' => $inactive]), $empty_category->publicUrl(), $draft->publicUrl(), $scheduled->publicUrl()] as $url) {
            $this->assertStringNotContainsString('<loc>'.$url.'</loc>', $xml);
        }
    }

    /**
     * robots.txt points at the sitemap and keeps crawlers out of private areas.
     *
     * @return void
     */
    public function test_robots_txt(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'), 'A static robots.txt would be served instead of the generated one.');

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /track/');
    }

    /**
     * Homepage, category, about and contact pages carry a title, description,
     * canonical URL and Open Graph tags.
     *
     * @return void
     */
    public function test_pages_have_meta_and_open_graph_tags(): void
    {
        $category = ServiceCategory::factory()->create(['slug' => PublicNavigation::ADMISSION_CATEGORY_SLUG, 'name' => 'Admission Services', 'description' => 'Help getting into university.']);

        $pages = [
            route('home') => 'Made Easy Educational Consultancy | WAEC, NECO, JAMB &amp; Admission Services',
            $category->publicUrl() => 'Admission Services | Made Easy Educational Consultancy',
            route('about') => 'About Us | Made Easy Educational Consultancy',
            route('contact') => 'Contact | Made Easy Educational Consultancy',
        ];

        foreach ($pages as $url => $title) {
            $this->get($url)
                ->assertOk()
                ->assertSee("<title>{$title}</title>", false)
                ->assertSee('<meta name="description" content="', false)
                ->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('<meta property="og:url" content="'.$url.'">', false)
                ->assertSee('<meta property="og:title" content="'.$title.'">', false)
                ->assertSee('<meta property="og:type" content="website">', false)
                ->assertSee('<meta property="og:description"', false);
        }

        $this->get($category->publicUrl())->assertSee('<meta property="og:description" content="Help getting into university.">', false);
    }

    /**
     * The floating button is on every public page with a generic message,
     * names the service on a service page, and asks about tutoring on the
     * maths page.
     *
     * @return void
     */
    public function test_whatsapp_button_with_contextual_messages(): void
    {
        config(['madeeasy.contact.whatsapp' => '0803 123 4567']);
        $this->assertSame('2348031234567', WhatsApp::number());

        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->for($category, 'category')->create(['name' => 'WAEC Registration', 'is_active' => true]);

        foreach ([route('home'), route('about'), route('faq'), route('updates'), route('services.index')] as $url) {
            $this->get($url)->assertSee('href="'.e(WhatsApp::link()).'"', false);
        }

        $this->get(route('services.show', ['category' => $category, 'service' => $service]))
            ->assertSee('https://wa.me/2348031234567?text='.rawurlencode("Hi Made Easy, I'd like to know more about WAEC Registration."), false);

        $this->get(route('maths-tutoring'))
            ->assertOk()
            ->assertSee('arranged directly with our team on WhatsApp')
            ->assertSee(e(WhatsApp::link(WhatsApp::tutoring_message())), false);
        $this->get(route('home'))->assertSee(route('maths-tutoring'));

        config(['madeeasy.contact.whatsapp' => 'to be confirmed']);
        $this->get(route('home'))->assertDontSee('data-testid="whatsapp-button"', false);
    }
}
