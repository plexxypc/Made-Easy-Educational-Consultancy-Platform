<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\ServiceCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Caddy terminates TLS (or a tunnel does, in front of Caddy) and talks to
 * PHP-FPM over plain HTTP, so Laravel must honour X-Forwarded-* to build
 * https:// URLs. Configured in bootstrap/app.php (trustProxies).
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    /** @var string Public host the proxy forwards for. */
    private const PUBLIC_HOST = 'madeeasy.example';

    /** @var string Caddy's address on the Docker network. */
    private const CADDY_IP = '172.19.0.5';

    /**
     * Ignore ASSET_URL from a developer's .env so every URL on the page has
     * to come from the forwarded request.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        URL::useAssetOrigin(null);
    }

    /**
     * X-Forwarded-Proto/-Host/-For from Caddy are honoured; without them the
     * request stays plain HTTP.
     *
     * @return void
     */
    public function test_forwarded_headers_are_honoured(): void
    {
        Route::get('_proxy-probe', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'url' => url('/somewhere'),
        ]);

        $this->via_caddy()
            ->get('http://app/_proxy-probe')
            ->assertOk()
            ->assertExactJson([
                'secure' => true,
                'host' => self::PUBLIC_HOST,
                'ip' => '102.88.169.218',
                'url' => 'https://'.self::PUBLIC_HOST.'/somewhere',
            ]);

        $this->flushHeaders()
            ->withServerVariables(['REMOTE_ADDR' => self::CADDY_IP])
            ->get('http://app/_proxy-probe')
            ->assertOk()
            ->assertJsonPath('secure', false)
            ->assertJsonPath('url', 'http://app/somewhere');
    }

    /**
     * Livewire's injected script tag (/livewire-{hash}/livewire.js) and its
     * update endpoint use https:// behind the proxy.
     *
     * @return void
     */
    public function test_livewire_script_tag_uses_https(): void
    {
        $response = $this->via_caddy()->get('http://app/login')->assertOk();

        $this->assertMatchesRegularExpression(
            '#<script src="https://'.preg_quote(self::PUBLIC_HOST, '#').'/livewire-[^/"]+/livewire(\.min)?\.js#',
            $response->getContent(),
        );
        $this->assert_no_insecure_urls($response, '/login');
    }

    /**
     * Public pages, Livewire auth pages and signed-in pages render no
     * http:// links, forms or assets back to the site.
     *
     * @return void
     */
    public function test_pages_render_only_https_urls(): void
    {
        (new ServiceCatalogueSeeder)->run();

        $guest_pages = [
            '/', '/services', '/about', '/contact', '/faq', '/updates', '/testimonials',
            '/result-checker', '/book-consultation', '/track', '/maths-tutoring',
            '/login', '/register', '/forgot-password',
        ];

        foreach ($guest_pages as $path) {
            $this->assert_no_insecure_urls($this->via_caddy()->get('http://app'.$path), $path);
        }

        $this->actingAs(User::factory()->create());

        foreach (['/my-requests', '/profile'] as $path) {
            $this->assert_no_insecure_urls($this->via_caddy()->get('http://app'.$path), $path);
        }
    }

    /**
     * Send the next request as Caddy would after terminating TLS.
     *
     * @return static
     */
    private function via_caddy(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::CADDY_IP])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => self::PUBLIC_HOST,
                'X-Forwarded-For' => '102.88.169.218',
            ]);
    }

    /**
     * The page rendered, links back to the site over https, and never over http.
     *
     * @param  TestResponse  $response
     * @param  string  $path
     * @return void
     */
    private function assert_no_insecure_urls(TestResponse $response, string $path): void
    {
        $this->assertSame(200, $response->getStatusCode(), "{$path} answered {$response->getStatusCode()}.");

        $content = (string) $response->getContent();

        $this->assertStringContainsString('https://'.self::PUBLIC_HOST, $content, "{$path} has no https:// link to the site.");
        $this->assertStringNotContainsString('http://'.self::PUBLIC_HOST, $content, "{$path} links to the site over http://.");
        $this->assertStringNotContainsString('http:\/\/'.self::PUBLIC_HOST, $content, "{$path} has an http:// URL in inline JSON.");
        $this->assertStringNotContainsString('http://app', $content, "{$path} leaks the internal upstream host.");
    }
}
