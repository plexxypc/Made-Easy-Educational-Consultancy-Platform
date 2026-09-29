<?php

namespace Tests\Feature\Filament;

use App\Enums\CustomerFieldType;
use App\Enums\FulfilmentType;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Filament\Resources\Services\Pages\ListServices;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Database\Seeders\ServiceCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Service admin: naira input stored as kobo, quote pricing, placeholder filter.
 */
class ServiceResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    /**
     * Price is typed in naira and stored as kobo; customer fields are saved as JSON.
     *
     * @return void
     */
    public function test_create_service_stores_price_in_kobo(): void
    {
        $category = ServiceCategory::factory()->create();

        Livewire::test(CreateService::class)
            ->fillForm([
                'service_category_id' => $category->id,
                'name' => 'JAMB Change of Course',
                'slug' => 'jamb-change-of-course',
                'fulfilment_type' => FulfilmentType::Manual->value,
                'price' => '6500.50',
                'is_active' => true,
                'customer_fields' => [
                    [
                        'label' => 'JAMB registration number',
                        'key' => 'jamb_registration_number',
                        'type' => CustomerFieldType::Text->value,
                        'required' => true,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $service = Service::query()->where('slug', 'jamb-change-of-course')->firstOrFail();

        $this->assertSame(650_050, $service->price);
        $this->assertSame('jamb_registration_number', $service->customer_fields[0]['key']);
        $this->assertTrue((bool) $service->customer_fields[0]['required']);
    }

    /**
     * The edit form shows naira, not raw kobo.
     *
     * @return void
     */
    public function test_edit_form_shows_naira(): void
    {
        $service = Service::factory()->create(['price' => 650_000]);

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->assertSchemaStateSet(['price' => '6500']);
    }

    /**
     * Invalid naira input is rejected by validation.
     *
     * @return void
     */
    public function test_invalid_price_is_rejected(): void
    {
        $service = Service::factory()->create();

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['price' => '6,500'])
            ->call('save')
            ->assertHasFormErrors(['price' => 'regex']);
    }

    /**
     * Quote services are always priced at ₦0 regardless of input.
     *
     * @return void
     */
    public function test_quote_service_is_saved_at_zero(): void
    {
        $service = Service::factory()->create(['price' => 500_000]);

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm(['fulfilment_type' => FulfilmentType::Quote->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $service->refresh()->price);
    }

    /**
     * The seeded duplicate pairs exist and are flagged by the "possible duplicates" filter.
     *
     * @return void
     */
    public function test_seeded_duplicates_are_flagged(): void
    {
        (new ServiceCatalogueSeeder)->run();

        $duplicate_slugs = array_keys(Service::POSSIBLE_DUPLICATES);
        $duplicates = Service::query()->whereIn('slug', $duplicate_slugs)->get();
        $this->assertCount(4, $duplicates);

        $unique_service = Service::query()->whereNotIn('slug', $duplicate_slugs)->firstOrFail();

        Livewire::test(ListServices::class)
            ->filterTable('possible_duplicates')
            ->assertCanSeeTableRecords($duplicates)
            ->assertCanNotSeeTableRecords([$unique_service])
            ->assertSee('possible duplicate of olevel-upload-assistance');

        Livewire::test(EditService::class, ['record' => $duplicates->firstWhere('slug', 'olevel-result-upload')->getRouteKey()])
            ->assertSee('Possible duplicate');
    }

    /**
     * The "inactive placeholders" filter shows only inactive services on the placeholder price.
     *
     * @return void
     */
    public function test_inactive_placeholder_filter(): void
    {
        $placeholder = Service::factory()->create(['is_active' => false, 'price' => Service::PLACEHOLDER_PRICE]);
        $inactive_real_price = Service::factory()->create(['is_active' => false, 'price' => 650_000]);
        $active = Service::factory()->create(['is_active' => true]);

        Livewire::test(ListServices::class)
            ->assertCanSeeTableRecords([$placeholder, $inactive_real_price, $active])
            ->filterTable('inactive_placeholders')
            ->assertCanSeeTableRecords([$placeholder])
            ->assertCanNotSeeTableRecords([$inactive_real_price, $active]);
    }
}
