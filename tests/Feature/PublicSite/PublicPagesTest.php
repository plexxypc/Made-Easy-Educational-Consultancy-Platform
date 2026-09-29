<?php

namespace Tests\Feature\PublicSite;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\PublicNavigation;
use Database\Seeders\ServiceCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Homepage, navigation, About and Contact.
 */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The homepage uses the PDF headline/description and the four main buttons.
     *
     * @return void
     */
    public function test_homepage_shows_pdf_copy_and_main_buttons(): void
    {
        (new ServiceCatalogueSeeder)->run();

        $response = $this->get('/')->assertOk();

        $response->assertSee('Making Your Educational Journey Easier');
        $response->assertSee('Made Easy Educational Consultancy provides reliable educational and documentation services,', false);
        $response->assertSee('JAMB services, and other essential educational support.', false);

        foreach (['Explore Our Services', 'Get Admission Guidance', 'Contact Us', 'Make Payment'] as $button_label) {
            $response->assertSee($button_label);
        }

        $guidance = Service::query()->with('category')->where('slug', Service::ADMISSION_GUIDANCE_SLUG)->sole();
        $response->assertSee(route('services.show', ['category' => $guidance->category, 'service' => $guidance]), false);
    }

    /**
     * The services overview lists categories from the database, skipping ones with no active services.
     *
     * @return void
     */
    public function test_homepage_lists_live_categories_with_active_services(): void
    {
        $live_category = ServiceCategory::factory()->create(['name' => 'Live Category Xyz']);
        Service::factory()->for($live_category, 'category')->create(['is_active' => true]);

        $empty_category = ServiceCategory::factory()->create(['name' => 'Dormant Category Xyz']);
        Service::factory()->for($empty_category, 'category')->create(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Live Category Xyz')
            ->assertSee('1 service')
            ->assertDontSee('Dormant Category Xyz');
    }

    /**
     * Main nav matches the PDF exactly, and every link resolves.
     *
     * @return void
     */
    public function test_navigation_matches_pdf_and_every_link_works(): void
    {
        (new ServiceCatalogueSeeder)->run();

        $nav_items = PublicNavigation::items(Request::create('/'));

        $this->assertSame(
            ['Home', 'Services', 'Admission', 'Result Checker', 'Documents', 'Updates', 'About Us', 'Contact'],
            array_column($nav_items, 'label'),
        );

        $home = $this->get('/');

        foreach ($nav_items as $item) {
            $home->assertSee('href="'.$item['url'].'"', false);
            $this->get($item['url'])->assertOk();
        }
    }

    /**
     * The current section is marked in the nav.
     *
     * @return void
     */
    public function test_current_nav_item_is_marked(): void
    {
        (new ServiceCatalogueSeeder)->run();

        $this->get(route('services.category', ['category' => PublicNavigation::DOCUMENTS_CATEGORY_SLUG]))
            ->assertOk()
            ->assertSee('aria-current="page"', false);

        $nav_items = collect(PublicNavigation::items(
            tap(Request::create('/contact'), fn (Request $request) => $request->setRouteResolver(
                fn () => app('router')->getRoutes()->match($request),
            )),
        ));

        $this->assertSame(['Contact'], $nav_items->where('active', true)->pluck('label')->all());
    }

    /**
     * Contact details come from config, not the template.
     *
     * @return void
     */
    public function test_contact_page_uses_configured_details(): void
    {
        config([
            'madeeasy.contact.business_name' => 'Made Easy Test Office',
            'madeeasy.contact.phone' => '+234 803 111 2222',
            'madeeasy.contact.whatsapp' => '+234 803 333 4444',
            'madeeasy.contact.email' => 'office@example.test',
            'madeeasy.contact.address' => '12 Example Road, Osogbo',
        ]);

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Made Easy Test Office')
            ->assertSee('+234 803 111 2222')
            ->assertSee('href="tel:+2348031112222"', false)
            ->assertSee('+234 803 333 4444')
            ->assertSee('mailto:office@example.test', false)
            ->assertSee('12 Example Road, Osogbo');
    }

    /**
     * About and the placeholder pages render.
     *
     * @return void
     */
    public function test_about_and_placeholder_pages_render(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('About');
        $this->get(route('result-checker'))->assertOk()->assertSee('Buy Result Checker');
        $this->get(route('updates'))->assertOk()->assertSee('Admission Updates');
    }
}
