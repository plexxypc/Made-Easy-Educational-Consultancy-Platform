<?php

namespace App\Models;

use App\Enums\FulfilmentType;
use App\Enums\PinStockLevel;
use App\Support\Money;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
 *                                                                                                                                                                 Shape documented in the create_services_table migration.
 * @property int|null $pin_low_stock_threshold Result checkers: "low" at or below this; null = the global default.
 * @property PinStockLevel|null $pin_stock_level Last level seen by CheckPinStockLevel (not mass-assignable).
 * @property Carbon|null $pin_stock_level_changed_at
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
    'pin_low_stock_threshold',
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
     * checker store, and consultations are booked on /book-consultation, so
     * neither takes a generic request.
     *
     * @return bool
     */
    public function acceptsRequests(): bool
    {
        return $this->is_active && ! in_array($this->fulfilment_type, [FulfilmentType::Instant, FulfilmentType::Consultation], true);
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
            'pin_low_stock_threshold' => 'integer',
            'pin_stock_level' => PinStockLevel::class,
            'pin_stock_level_changed_at' => 'datetime',
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
     * Whether this service is a result checker (instant fulfilment from PIN stock).
     *
     * @return bool
     */
    public function isResultChecker(): bool
    {
        return $this->fulfilment_type === FulfilmentType::Instant;
    }

    /**
     * Available-PIN count at or below which this result checker is "low":
     * its own value if set, otherwise config('madeeasy.pins.low_stock_threshold').
     *
     * @return int
     */
    public function lowStockThreshold(): int
    {
        return $this->pin_low_stock_threshold ?? (int) config('madeeasy.pins.low_stock_threshold');
    }

    /**
     * Whether this service is a consultation booked on /book-consultation.
     *
     * @return bool
     */
    public function isConsultation(): bool
    {
        return $this->fulfilment_type === FulfilmentType::Consultation;
    }

    /**
     * The consultation service sold on /book-consultation, or null if none is
     * on sale (e.g. "Admission Consultation" not yet activated in the admin).
     *
     * Assumption: one consultation service at a time. Slots are shared staff
     * time, not tied to a service; if several consultation services are
     * active, the oldest is booked.
     *
     * @return self|null
     */
    public static function bookableConsultation(): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('fulfilment_type', FulfilmentType::Consultation->value)
            ->where('price', '>', 0)
            ->orderBy('id')
            ->first();
    }

    /**
     * Active result checkers, as sold on the "Buy Result Checker" page.
     *
     * @param  Builder<Service>  $query
     * @return void
     */
    #[Scope]
    protected function resultCheckersForSale(Builder $query): void
    {
        $query->where('is_active', true)
            ->where('fulfilment_type', FulfilmentType::Instant->value)
            ->where('price', '>', 0);
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

    /**
     * Result checker PIN stock for this service (all statuses).
     *
     * @return HasMany<ResultCheckerPin, $this>
     */
    public function pins(): HasMany
    {
        return $this->hasMany(ResultCheckerPin::class);
    }
}
