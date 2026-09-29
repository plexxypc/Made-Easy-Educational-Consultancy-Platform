<?php

namespace App\Providers;

use App\Models\Order;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->register_blade_directives();
        $this->register_rate_limiters();
    }

    /**
     * "service-requests": public request form submissions, per IP.
     * Assumption: 5 per minute is plenty for a real customer and slows spam bots.
     *
     * "order-lookups": the "Track your request" form. Same per-IP limit as the
     * request form, plus a per-reference hourly cap so one order can't be
     * brute-forced from many IPs. Over the limit, the visitor is sent back to
     * the form with a message instead of a bare 429 page.
     *
     * "order-responses": needs-action replies (already access-checked), per IP.
     *
     * "order-payments": "Pay now" and the Paystack return page (which calls
     * Paystack's verify API), per IP. The return page refreshes itself while
     * waiting for the webhook, hence a higher limit.
     *
     * @return void
     */
    private function register_rate_limiters(): void
    {
        RateLimiter::for('service-requests', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('order-lookups', function (Request $request): array {
            $too_many_attempts = fn () => back()
                ->withInput($request->except('contact'))
                ->withErrors(['reference' => 'Too many attempts. Please wait a few minutes and try again.']);
            $reference = Order::normaliseReference((string) $request->input('reference'));

            return [
                Limit::perMinute(config('madeeasy.order_lookups.per_minute_per_ip'))
                    ->by('ip:'.$request->ip())
                    ->response($too_many_attempts),
                Limit::perHour(config('madeeasy.order_lookups.per_hour_per_reference'))
                    ->by('reference:'.$reference)
                    ->response($too_many_attempts),
            ];
        });

        RateLimiter::for('order-responses', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('order-payments', fn (Request $request): Limit => Limit::perMinute(10)->by($request->ip()));
    }

    /**
     * `@role('admin', 'staff') ... @else ... @endrole` for Blade and Livewire views.
     *
     * This only controls what is displayed. The matching server-side check
     * (middleware, policy or `hasRole()` in the action) is still required.
     *
     * @return void
     */
    private function register_blade_directives(): void
    {
        Blade::if('role', function (string ...$roles): bool {
            return auth()->user()?->hasRole(...$roles) ?? false;
        });
    }
}
