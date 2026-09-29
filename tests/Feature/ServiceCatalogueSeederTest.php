<?php

namespace Tests\Feature;

use App\Enums\FulfilmentType;
use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\ServiceCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceCatalogueSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The catalogue seeds the four categories and applies the pricing rules.
     *
     * @return void
     */
    public function test_catalogue_is_seeded_with_documented_prices(): void
    {
        $this->seed(ServiceCatalogueSeeder::class);

        $this->assertSame(
            ['examination-services', 'admission-services', 'document-services', 'result-checkers'],
            ServiceCategory::query()->orderBy('sort_order')->pluck('slug')->all(),
        );
        $this->assertSame(40, Service::query()->count());

        $waec_checker = Service::query()->where('slug', 'waec-result-checker')->sole();
        $this->assertSame(650_000, $waec_checker->price);
        $this->assertSame(FulfilmentType::Instant, $waec_checker->fulfilment_type);
        $this->assertTrue($waec_checker->is_active);

        $consultation = Service::query()->where('slug', 'admission-consultation')->sole();
        $this->assertSame(2_000_000, $consultation->price);
        $this->assertTrue($consultation->is_active);

        $neco_checker = Service::query()->where('slug', 'neco-result-checker')->sole();
        $this->assertSame(Service::PLACEHOLDER_PRICE, $neco_checker->price);
        $this->assertTrue($neco_checker->isInactivePlaceholder());
        $this->assertSame(26, Service::query()->inactivePlaceholder()->count());
        $this->assertFalse($neco_checker->is_active, 'Placeholder-priced services must not be purchasable.');

        $this->assertSame(
            0,
            Service::query()->where('fulfilment_type', FulfilmentType::Quote)->where('price', '!=', 0)->count(),
        );

        $guidance = Service::query()->where('slug', 'admission-guidance')->sole();
        $this->assertSame(
            ['jamb_score', 'olevel_results', 'preferred_course', 'preferred_institution', 'admission_mode', 'question_or_problem'],
            array_column($guidance->customer_fields, 'key'),
        );
    }

    /**
     * Re-seeding does not duplicate rows or overwrite admin edits.
     *
     * @return void
     */
    public function test_reseeding_is_idempotent_and_non_destructive(): void
    {
        $this->seed(ServiceCatalogueSeeder::class);

        $neco_checker = Service::query()->where('slug', 'neco-result-checker')->sole();
        $neco_checker->update(['price' => 150_000, 'is_active' => true]);

        $this->seed(ServiceCatalogueSeeder::class);

        $this->assertSame(40, Service::query()->count());
        $this->assertSame(4, ServiceCategory::query()->count());
        $this->assertSame(150_000, $neco_checker->fresh()->price);
        $this->assertTrue($neco_checker->fresh()->is_active);
    }
}
