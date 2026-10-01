<?php

namespace App\Http\Controllers;

use App\Enums\SiteSettingKey;
use App\Models\Faq;
use App\Models\Post;
use App\Models\ServiceCategory;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Support\PublicNavigation;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Number of FAQs shown on the homepage (the rest are on /faq).
     *
     * @var int
     */
    public const HOME_FAQ_COUNT = 4;

    /**
     * Homepage: live categories with active services, the trust figure
     * (Site settings), the newest updates, approved testimonials and the
     * first few FAQs. Everything is read fresh, so admin edits show at once.
     *
     * @return View
     */
    public function __invoke(): View
    {
        return view('pages.home', [
            'categories' => ServiceCategory::query()->withActiveServices()->get(),
            'admission_guidance_url' => PublicNavigation::admission_guidance_url(),
            'latest_posts' => Post::query()->published()->with('category')->newestFirst()->limit(3)->get(),
            'students_assisted' => SiteSetting::current(SiteSettingKey::StudentsAssisted),
            'students_assisted_label' => SiteSetting::current(SiteSettingKey::StudentsAssistedLabel),
            'testimonials' => Testimonial::query()->approved()->limit(3)->get(),
            'faqs' => Faq::query()->shown()->limit(self::HOME_FAQ_COUNT)->get(),
            'has_more_faqs' => Faq::query()->shown()->count() > self::HOME_FAQ_COUNT,
        ]);
    }
}
