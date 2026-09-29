<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

/**
 * Main site navigation (requirements PDF section 19):
 * HOME | SERVICES | ADMISSION | RESULT CHECKER | DOCUMENTS | UPDATES | ABOUT US | CONTACT
 *
 * "Admission" and "Documents" are category pages, found by slug. If an admin
 * changes those category slugs, update the constants below.
 */
class PublicNavigation
{
    /**
     * @var string
     */
    public const ADMISSION_CATEGORY_SLUG = 'admission-services';

    /**
     * @var string
     */
    public const DOCUMENTS_CATEGORY_SLUG = 'document-services';

    /**
     * Nav items in display order.
     *
     * @param  Request  $request
     * @return array<int, array{label: string, url: string, active: bool}>
     */
    public static function items(Request $request): array
    {
        $current_category_slug = self::current_category_slug($request);
        $in_catalogue = $request->routeIs('services.*');

        return [
            ['label' => 'Home', 'url' => route('home'), 'active' => $request->routeIs('home')],
            [
                'label' => 'Services',
                'url' => route('services.index'),
                'active' => $in_catalogue && ! in_array($current_category_slug, [self::ADMISSION_CATEGORY_SLUG, self::DOCUMENTS_CATEGORY_SLUG], true),
            ],
            [
                'label' => 'Admission',
                'url' => route('services.category', ['category' => self::ADMISSION_CATEGORY_SLUG]),
                'active' => $in_catalogue && $current_category_slug === self::ADMISSION_CATEGORY_SLUG,
            ],
            ['label' => 'Result Checker', 'url' => route('result-checker'), 'active' => $request->routeIs('result-checker')],
            [
                'label' => 'Documents',
                'url' => route('services.category', ['category' => self::DOCUMENTS_CATEGORY_SLUG]),
                'active' => $in_catalogue && $current_category_slug === self::DOCUMENTS_CATEGORY_SLUG,
            ],
            ['label' => 'Updates', 'url' => route('updates'), 'active' => $request->routeIs('updates')],
            ['label' => 'About Us', 'url' => route('about'), 'active' => $request->routeIs('about')],
            ['label' => 'Contact', 'url' => route('contact'), 'active' => $request->routeIs('contact')],
        ];
    }

    /**
     * Where "Get Admission Guidance" goes: the Admission Guidance request form,
     * or the Admission category if that service is missing or inactive.
     *
     * @return string
     */
    public static function admission_guidance_url(): string
    {
        $service = Service::query()
            ->active()
            ->with('category')
            ->where('slug', Service::ADMISSION_GUIDANCE_SLUG)
            ->first();

        return $service
            ? route('services.show', ['category' => $service->category, 'service' => $service])
            : route('services.category', ['category' => self::ADMISSION_CATEGORY_SLUG]);
    }

    /**
     * Slug of the category in the current catalogue route, if any.
     *
     * @param  Request  $request
     * @return string|null
     */
    private static function current_category_slug(Request $request): ?string
    {
        $category = $request->route('category');

        return $category instanceof ServiceCategory ? $category->slug : (is_string($category) ? $category : null);
    }
}
