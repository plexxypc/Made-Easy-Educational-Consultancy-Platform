<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Role gates are applied here, per file, so a route added to
            // routes/staff.php or routes/admin.php can't forget them.
            // RoleProtectedRoutesTest fails if any /staff or /admin route lacks one.
            Route::middleware(['web', 'auth', 'role:staff,admin'])
                ->prefix('staff')
                ->name('staff.')
                ->group(base_path('routes/staff.php'));

            Route::middleware(['web', 'auth', 'role:admin'])
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Caddy is the only thing that can reach PHP-FPM (it never leaves the
        // Docker network), and it talks to us over plain HTTP. Trusting its
        // X-Forwarded-* headers is what makes url(), redirects, secure cookies
        // and Livewire's script tag use https:// instead of http://.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        // Paystack can't send a CSRF token; the webhook checks its HMAC signature instead.
        $middleware->preventRequestForgery(except: ['webhooks/paystack']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
