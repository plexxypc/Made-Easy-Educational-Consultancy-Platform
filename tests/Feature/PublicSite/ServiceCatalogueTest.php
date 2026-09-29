<?php

namespace Tests\Feature\PublicSite;

use App\Enums\FulfilmentType;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public catalogue: only active services, clear Naira pricing.
 */
class ServiceCatalogueTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var ServiceCategory
     */
    private ServiceCategory $category;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ServiceCategory::factory()->create(['name' => 'Examination Services', 'slug' => 'examination-services']);
    }

    /**
     * Inactive services are not listed anywhere and their pages 404.
     *
     * @return void
     */
    public function test_only_active_services_are_listed(): void
    {
        $active = Service::factory()->for($this->category, 'category')->create(['name' => 'Active Service Abc', 'is_active' => true]);
        $inactive = Service::factory()->for($this->category, 'category')->create(['name' => 'Hidden Service Abc', 'is_active' => false]);

        $this->get(route('services.category', $this->category))
            ->assertOk()
            ->assertSee('Active Service Abc')
            ->assertDontSee('Hidden Service Abc');

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('Active Service Abc')
            ->assertDontSee('Hidden Service Abc');

        $this->get(route('services.show', ['category' => $this->category, 'service' => $active]))->assertOk();
        $this->get(route('services.show', ['category' => $this->category, 'service' => $inactive]))->assertNotFound();
    }

    /**
     * Quote services show "Price on request", never ₦0; fixed prices show in naira.
     *
     * @return void
     */
    public function test_quote_service_shows_price_on_request(): void
    {
        $quote = Service::factory()->for($this->category, 'category')->create([
            'name' => 'Quote Service Abc',
            'fulfilment_type' => FulfilmentType::Quote,
        ]);
        Service::factory()->for($this->category, 'category')->create(['name' => 'Priced Service Abc', 'price' => 650_000]);

        $this->assertSame(0, $quote->price);

        $this->get(route('services.category', $this->category))
            ->assertOk()
            ->assertSee('Price on request')
            ->assertSee('₦6,500')
            ->assertDontSee('₦0');

        $this->get(route('services.show', ['category' => $this->category, 'service' => $quote]))
            ->assertOk()
            ->assertSee('Price on request')
            ->assertDontSee('₦0');
    }

    /**
     * A category with no active services shows an empty state, not an error.
     *
     * @return void
     */
    public function test_empty_category_shows_coming_soon(): void
    {
        $this->get(route('services.category', $this->category))
            ->assertOk()
            ->assertSee('coming soon');
    }

    /**
     * A service can only be reached under its own category.
     *
     * @return void
     */
    public function test_service_url_is_scoped_to_its_category(): void
    {
        $other_category = ServiceCategory::factory()->create();
        $service = Service::factory()->for($other_category, 'category')->create();

        $this->get(route('services.show', ['category' => $this->category, 'service' => $service]))->assertNotFound();
    }

    /**
     * Result checkers aren't sold through the generic request form.
     *
     * @return void
     */
    public function test_result_checkers_use_their_own_page(): void
    {
        $checkers = ServiceCategory::factory()->create(['slug' => ServiceCategory::RESULT_CHECKERS_SLUG]);
        $checker = Service::factory()->for($checkers, 'category')->create([
            'fulfilment_type' => FulfilmentType::Instant,
            'price' => 650_000,
        ]);

        $this->get(route('services.category', $checkers))->assertRedirect(route('result-checker'));

        $this->get(route('services.show', ['category' => $checkers, 'service' => $checker]))
            ->assertOk()
            ->assertDontSee('Request this service')
            ->assertSee('Go to Result Checker');

        $this->post(route('services.request', ['category' => $checkers, 'service' => $checker]), [
            'name' => 'Ada',
            'phone' => '08031234567',
            'email' => 'ada@example.com',
        ])->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
    }
}
