<?php

namespace Tests\Feature\Security;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Checks a RUNNING deployment through Caddy (docker/caddy/Caddyfile.prod):
 * secrets and source are 404, security headers are on dynamic and static
 * responses, and /up answers. Skipped unless PRODUCTION_CHECK_URL is set.
 *
 * Against the real site, from any machine with Docker (README, GO-LIVE.md):
 *   -e PRODUCTION_CHECK_URL=https://your-domain.com ... php artisan test --group=production
 *
 * Optional:
 *   PRODUCTION_CHECK_INSECURE=1  accept Caddy's internal CA (local drill only)
 *   PRODUCTION_CHECK_RESOLVE=localhost:443:172.18.0.5  pin the host to an IP (curl --resolve)
 *   PRODUCTION_CHECK_HSTS=31536000  also require this HSTS max-age
 */
#[Group('production')]
class ProductionDeploymentTest extends TestCase
{
    /**
     * Paths that must never be served.
     *
     * @return array<string, array{0: string}>
     */
    public static function never_served(): array
    {
        return [
            '.env' => ['/.env'],
            '.env.example' => ['/.env.example'],
            '.git config' => ['/.git/config'],
            '.git HEAD' => ['/.git/HEAD'],
            'dotfile in public/' => ['/.htaccess'],
            'vendor autoloader' => ['/vendor/autoload.php'],
            'vendor package list' => ['/vendor/composer/installed.json'],
            'composer.json' => ['/composer.json'],
            'artisan' => ['/artisan'],
            'app log' => ['/storage/logs/laravel.log'],
            'private upload' => ['/storage/app/private/order-documents/example.pdf'],
            'sessions' => ['/storage/framework/sessions/example'],
            'config source' => ['/config/app.php'],
        ];
    }

    /**
     * @param  string  $path
     * @return void
     */
    #[DataProvider('never_served')]
    public function test_path_is_not_served(string $path): void
    {
        $response = $this->client()->get($path);

        $this->assertSame(404, $response->status(), "{$path} answered {$response->status()}.");
        $this->assertStringNotContainsString('APP_KEY', $response->body());
    }

    /**
     * Security headers on a Laravel page and on a static file Caddy serves
     * directly; no Server banner.
     *
     * @return void
     */
    public function test_security_headers_on_dynamic_and_static_responses(): void
    {
        foreach (['/', '/favicon.ico'] as $path) {
            $response = $this->client()->get($path);

            $this->assertSame(200, $response->status(), "{$path} answered {$response->status()}.");
            $this->assertSame('nosniff', $response->header('X-Content-Type-Options'), $path);
            $this->assertSame('SAMEORIGIN', $response->header('X-Frame-Options'), $path);
            $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'), $path);
            $this->assertSame('', $response->header('Server'), "{$path} reveals the server.");
            $this->assertCount(1, $response->toPsrResponse()->getHeader('X-Frame-Options'), "{$path} sends X-Frame-Options twice.");

            $hsts = env('PRODUCTION_CHECK_HSTS');
            if (filled($hsts)) {
                $this->assertSame("max-age={$hsts}", $response->header('Strict-Transport-Security'), $path);
            }
        }
    }

    /**
     * /up answers through Caddy.
     *
     * @return void
     */
    public function test_health_check_is_reachable(): void
    {
        $response = $this->client()->get('/up');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Application up', $response->body());
    }

    /**
     * An HTTP client for the deployment, or skip the test.
     *
     * @return PendingRequest
     */
    private function client(): PendingRequest
    {
        $url = env('PRODUCTION_CHECK_URL');

        if (blank($url)) {
            $this->markTestSkipped('Set PRODUCTION_CHECK_URL to check a running deployment.');
        }

        $curl = [];
        if (filled(env('PRODUCTION_CHECK_RESOLVE'))) {
            $curl[CURLOPT_RESOLVE] = [env('PRODUCTION_CHECK_RESOLVE')];
        }

        return Http::baseUrl(rtrim((string) $url, '/'))
            ->withoutRedirecting()
            ->timeout(15)
            ->withOptions([
                'verify' => ! filter_var(env('PRODUCTION_CHECK_INSECURE', false), FILTER_VALIDATE_BOOL),
                'curl' => $curl,
            ]);
    }
}
