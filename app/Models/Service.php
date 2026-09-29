<?php

namespace App\Models;

use App\Enums\FulfilmentType;
use App\Support\Money;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A purchasable service.
 *
 * @property int $id
 * @property int $service_category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price Amount in KOBO.
 * @property bool $is_active
 * @property FulfilmentType $fulfilment_type
 * @property array<int, array{key: string, label: string, type: string, required: bool, min?: int, max?: int, options?: array<int, string>}>|null $customer_fields
 *           Shape documented in the create_services_table migration.
 */
#[Fillable([
    'service_category_id',
    'name',
    'slug',
    'description',
    'price',
    'is_active',
    'fulfilment_type',
    'customer_fields',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Price (kobo) given to seeded services whose real price isn't known yet: ₦1,000.
     * Such services are seeded inactive; see isInactivePlaceholder().
     *
     * @var int
     */
    public const PLACEHOLDER_PRICE = 100_000;

    /**
     * Seeded services the requirements PDF lists twice under different sections
     * (slug => the other slug). Flagged in the admin until the client decides
     * whether to merge or keep both.
     *
     * @var array<string, string>
     */
    public const POSSIBLE_DUPLICATES = [
        'waec-neco-certificate-processing' => 'waec-neco-gce-nabteb-certificate-processing',
        'waec-neco-gce-nabteb-certificate-processing' => 'waec-neco-certificate-processing',
        'olevel-result-upload' => 'olevel-upload-assistance',
        'olevel-upload-assistance' => 'olevel-result-upload',
    ];

    /**
     * Slug of the seeded "Admission Guidance" service, which backs the
     * homepage "Get Admission Guidance" button.
     *
     * @var string
     */
    public const ADMISSION_GUIDANCE_SLUG = 'admission-guidance';

    /**
     * Public price text: "Price on request" for quote services, otherwise naira.
     *
     * @return string
     */
    public function priceLabel(): string
    {
        return $this->fulfilment_type === FulfilmentType::Quote
            ? 'Price on request'
            : Money::format($this->price, hide_zero_kobo: true);
    }

    /**
     * Whether customers can submit the generic "Request this service" form.
     *
     * Instant services (result checker PINs) are sold through the result
     * checker store instead, so they never take a generic request.
     *
     * @return bool
     */
    public function acceptsRequests(): bool
    {
        return $this->is_active && $this->fulfilment_type !== FulfilmentType::Instant;
    }

    /**
     * Slug of the service this one may duplicate, if it is a known duplicate.
     *
     * @return string|null
     */
    public function possibleDuplicateSlug(): ?string
    {
        return self::POSSIBLE_DUPLICATES[$this->slug] ?? null;
    }

    /**
     * Quote services are always priced at 0; staff set each order's total instead.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (Service $service): void {
            if ($service->fulfilment_type === FulfilmentType::Quote) {
                $service->price = 0;
            }
        });
    }

    /**
     * Inactive, non-quote service still carrying the seeded placeholder price.
     *
     * @return bool
     */
    public function isInactivePlaceholder(): bool
    {
        return ! $this->is_active
            && $this->fulfilment_type !== FulfilmentType::Quote
            && $this->price === self::PLACEHOLDER_PRICE;
    }

    /**
     * Services matching isInactivePlaceholder().
     *
     * @param  Builder<Service>  $query
     * @return void
     */
    #[Scope]
    protected function inactivePlaceholder(Builder $query): void
    {
        $query->where('is_active', false)
            ->where('fulfilment_type', '!=', FulfilmentType::Quote->value)
            ->where('price', self::PLACEHOLDER_PRICE);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
            'fulfilment_type' => FulfilmentType::class,
            'customer_fields' => 'array',
        ];
    }

    /**
     * Only services that are currently offered.
     *
     * @param  Builder<Service>  $query
     * @return void
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The category this service belongs to.
     *
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * Orders placed for this service.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
