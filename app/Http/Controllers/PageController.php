<?php

namespace App\Http\Controllers;

use App\Enums\SiteSettingKey;
use App\Models\Faq;
use App\Models\SiteSetting;
use App\Support\WhatsApp;
use Illuminate\View\View;

/**
 * Simple public pages whose content comes from the back office.
 */
class PageController extends Controller
{
    /**
     * About Us: body text from Site settings.
     *
     * @return View
     */
    public function about(): View
    {
        return view('pages.about', [
            'paragraphs' => SiteSetting::paragraphs(SiteSettingKey::AboutBody),
        ]);
    }

    /**
     * All active FAQs, with FAQPage structured data.
     *
     * @return View
     */
    public function faq(): View
    {
        return view('pages.faq', [
            'faqs' => Faq::query()->shown()->get(),
        ]);
    }

    /**
     * Mathematics tutoring promo (PDF section 12). Arranged over WhatsApp
     * only: nothing is booked or paid on the site.
     *
     * @return View
     */
    public function maths_tutoring(): View
    {
        return view('pages.maths-tutoring', [
            'whatsapp_message' => WhatsApp::tutoring_message(),
        ]);
    }
}
