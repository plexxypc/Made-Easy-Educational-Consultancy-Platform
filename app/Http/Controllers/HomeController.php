<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\ServiceCategory;
use App\Support\PublicNavigation;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Homepage with a live overview of categories that have active services
     * and the three newest published updates.
     *
     * @return View
     */
    public function __invoke(): View
    {
        return view('pages.home', [
            'categories' => ServiceCategory::query()->withActiveServices()->get(),
            'admission_guidance_url' => PublicNavigation::admission_guidance_url(),
            'latest_posts' => Post::query()->published()->with('category')->newestFirst()->limit(3)->get(),
        ]);
    }
}
