<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\PublicNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Public service catalogue. Only active services are ever listed or shown.
 */
class ServiceCatalogueController extends Controller
{
    /**
     * All categories that currently have active services.
     *
     * @return View
     */
    public function index(): View
    {
        return view('services.index', [
            'categories' => ServiceCategory::query()
                ->withActiveServices()
                ->with('activeServices')
                ->get(),
        ]);
    }

    /**
     * One category's active services. Result checkers redirect to their own page.
     *
     * @param  ServiceCategory  $category
     * @return View|RedirectResponse
     */
    public function category(ServiceCategory $category): View|RedirectResponse
    {
        if ($category->isResultCheckers()) {
            return redirect()->route('result-checker');
        }

        return view('services.category', [
            'category' => $category,
            'services' => $category->activeServices()->get(),
            'show_admission_guidance' => $category->slug === PublicNavigation::ADMISSION_CATEGORY_SLUG,
            'admission_guidance_url' => PublicNavigation::admission_guidance_url(),
        ]);
    }

    /**
     * Service detail page with the request form (inactive services 404).
     *
     * @param  ServiceCategory  $category
     * @param  Service  $service
     * @return View
     */
    public function show(ServiceCategory $category, Service $service): View
    {
        abort_unless($service->is_active, 404);

        return view('services.show', [
            'category' => $category,
            'service' => $service,
            'fields' => $service->customer_fields ?? [],
            'upload_rules' => config('madeeasy.uploads'),
        ]);
    }
}
