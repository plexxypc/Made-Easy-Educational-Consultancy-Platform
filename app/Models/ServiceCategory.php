<?php

namespace App\Models;

use Database\Factories\ServiceCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Top-level catalogue grouping, e.g. "Examination Services".
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 */
#[Fillable(['name', 'slug', 'description', 'sort_order'])]
class ServiceCategory extends Model
{
    /** @use HasFactory<ServiceCategoryFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Slug of the result checker category. Its services are sold through the
     * result checker store (a later prompt), not the generic request flow.
     *
     * @var string
     */
    public const RESULT_CHECKERS_SLUG = 'result-checkers';

    /**
     * Services in this category.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * Active services only, alphabetical (what the public catalogue shows).
     *
     * @return HasMany<Service, $this>
     */
    public function activeServices(): HasMany
    {
        return $this->services()->where('is_active', true)->orderBy('name');
    }

    /**
     * Categories with at least one active service, in admin sort order.
     *
     * @param  Builder<ServiceCategory>  $query
     * @return void
     */
    #[Scope]
    protected function withActiveServices(Builder $query): void
    {
        $query->whereHas('services', fn (Builder $service_query) => $service_query->where('is_active', true))
            ->withCount('activeServices')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Whether this is the result checker category.
     *
     * @return bool
     */
    public function isResultCheckers(): bool
    {
        return $this->slug === self::RESULT_CHECKERS_SLUG;
    }

    /**
     * Public URL for this category (the result checker page for result checkers).
     *
     * @return string
     */
    public function publicUrl(): string
    {
        return $this->isResultCheckers()
            ? route('result-checker')
            : route('services.category', $this);
    }
}
