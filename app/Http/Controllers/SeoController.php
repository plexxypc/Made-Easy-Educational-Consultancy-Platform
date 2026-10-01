<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Response;

/**
 * sitemap.xml and robots.txt, generated so they always carry the current
 * host (APP_URL) and only list what the public can actually open.
 */
class SeoController extends Controller
{
    /**
     * Public pages, categories and active services, and published posts.
     *
     * Left out on purpose: drafts/scheduled posts, inactive services, the
     * result-checker category (it redirects to /result-checker, which is
     * listed), search result URLs, and anything behind a sign-in or lookup.
     *
     * @return Response
     */
    public function sitemap(): Response
    {
        $urls = [];

        foreach (['home', 'services.index', 'admission.search', 'result-checker', 'book-consultation', 'updates', 'about', 'contact', 'faq', 'testimonials', 'maths-tutoring'] as $route) {
            $urls[] = ['loc' => route($route), 'lastmod' => null];
        }

        $categories = ServiceCategory::query()
            ->withActiveServices()
            ->with('activeServices')
            ->get()
            ->reject(fn (ServiceCategory $category): bool => $category->isResultCheckers());

        foreach ($categories as $category) {
            $urls[] = ['loc' => $category->publicUrl(), 'lastmod' => null];

            foreach ($category->activeServices as $service) {
                /** @var Service $service */
                $urls[] = [
                    'loc' => route('services.show', ['category' => $category, 'service' => $service]),
                    'lastmod' => $service->updated_at,
                ];
            }
        }

        foreach (PostCategory::query()->whereHas('posts', fn ($query) => $query->published())->orderBy('name')->get() as $post_category) {
            $urls[] = ['loc' => $post_category->publicUrl(), 'lastmod' => null];
        }

        foreach (Post::query()->published()->newestFirst()->get(['id', 'slug', 'updated_at']) as $post) {
            $urls[] = ['loc' => $post->publicUrl(), 'lastmod' => $post->updated_at];
        }

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Crawl everything public; keep crawlers out of the back office, account
     * and per-order pages (which need a sign-in or lookup anyway).
     *
     * @return Response
     */
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /my-requests',
            'Disallow: /profile',
            'Disallow: /documents/',
            'Disallow: /track/',
            'Disallow: /requests/',
            'Disallow: /updates/preview/',
            '',
            'Sitemap: '.route('sitemap'),
        ];

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
