<?php

namespace Tests\Feature\Security;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Security headers on Laravel's responses (App\Http\Middleware\SecurityHeaders).
 * Caddy sends the same set for static files; see ProductionDeploymentTest.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The standard headers are on public pages, error pages and the health check.
     *
     * @return void
     */
    public function test_headers_are_present_on_responses(): void
    {
        foreach (['/', '/services', '/does-not-exist', '/up'] as $path) {
            $this->get($path)
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
    }

    /**
     * HSTS stays off until HSTS_MAX_AGE is set, and is never sent over plain HTTP.
     *
     * @return void
     */
    public function test_hsts_only_when_enabled_and_over_https(): void
    {
        config(['madeeasy.security.hsts_max_age' => 0]);
        $this->get('https://localhost/')->assertHeaderMissing('Strict-Transport-Security');

        config(['madeeasy.security.hsts_max_age' => 31536000]);
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * The private disk has no /storage/{path} route (Laravel's "serve"
     * option): uploads are reachable only through OrderDocumentController.
     *
     * @return void
     */
    public function test_private_disk_is_not_served_by_a_route(): void
    {
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
        $this->get('/storage/order-documents/anything.pdf')->assertNotFound();
    }

    /**
     * /up answers 200 when the app and database are up, and 500 when the
     * database is unreachable (so a monitor notices).
     *
     * @return void
     */
    public function test_health_check_reports_database_state(): void
    {
        $this->get('/up')->assertOk()->assertSee('Application up');

        Exceptions::fake();
        $working_connection = config('database.default');
        config([
            'app.debug' => false,
            'database.connections.unreachable' => ['driver' => 'sqlite', 'database' => '/nonexistent/unreachable.sqlite', 'prefix' => ''],
            'database.default' => 'unreachable',
        ]);

        try {
            $this->get('/up')->assertStatus(500);
        } finally {
            config(['database.default' => $working_connection]);
        }

        Exceptions::assertReported(QueryException::class);
    }
}
