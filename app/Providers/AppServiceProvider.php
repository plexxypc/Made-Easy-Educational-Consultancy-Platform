<?php

namespace App\Providers;

use App\Models\Order;
use App\Support\FormThrottle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->register_health_checks();
    }

    /**
     * /up (Laravel's health route) also proves the database answers, so a
     * monitor or `curl /up` catches "PHP is up but Postgres is not" as a 500.
     *
     * @return void
     */
    private function register_health_checks(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->select('select 1');
        });
    }

    /**
     * Named route limiters. The numbers, and the reasoning behind them, are in
     * config('madeeasy.rate_limits'); see FormThrottle for the Livewire forms.
     *
     * "order-lookups" also caps each reference number per hour so one order
     * can't be brute-forced from many IPs, and sends the visitor back to the
     * form with a message instead of a bare 429 page.
     *
     * @return void
     */
    private function register_rate_limiters(): void
    {
        foreach (['service-requests', 'order-responses', 'testimonials', 'order-payments', 'course-search'] as $name) {
            RateLimiter::for($name, fn (Request $request): Limit => FormThrottle::route_limit($name)->by($name.':'.$request->ip()));
        }

        foreach (['account-claims', 'verification-links'] as $name) {
            RateLimiter::for($name, fn (Request $request): Limit => FormThrottle::route_limit($name)
                ->by($name.':'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        }

        RateLimiter::for('order-lookups', function (Request $request): array {
            $too_many_attempts = fn () => back()
                ->withInput($request->except('contact'))
                ->withErrors(['reference' => 'Too many attempts. Please wait a few minutes and try again.']);
            $reference = Order::normaliseReference((string) $request->input('reference'));

            return [
                FormThrottle::route_limit('order-lookups')
                    ->by('ip:'.$request->ip())
                    ->response($too_many_attempts),
                FormThrottle::route_limit('order-lookups-per-reference')
                    ->by('reference:'.$reference)
                    ->response($too_many_attempts),
            ];
        });
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
