<?php

namespace Tests\Feature\Security;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Support\FormThrottle;
use Closure;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Every public form's rate limit (config('madeeasy.rate_limits')) is
 * enforced: one test per form. The "Track your request" lookup limits are
 * tested in OrderTrackingTest (per IP and per reference).
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes that change state without a rate limit, on purpose.
     *
     * @var array<int, string>
     */
    private const UNTHROTTLED_ON_PURPOSE = [
        'webhooks/paystack', // authenticated by signature; Paystack retries failures
        'livewire/update', // Livewire forms limit themselves (FormThrottle)
        'livewire/upload-file',
        'logout',
    ];

    /**
     * No state-changing public route ships without a throttle middleware.
     * (/admin and /staff are behind sign-in and a role.)
     *
     * @return void
     */
    public function test_every_public_form_route_is_throttled(): void
    {
        $unthrottled = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->reject(fn (Route $route): bool => str_starts_with($route->uri(), 'admin') || str_starts_with($route->uri(), 'staff'))
            ->reject(fn (Route $route): bool => $route->getActionName() === '\Illuminate\Routing\RedirectController')
            ->reject(fn (Route $route): bool => in_array($route->uri(), self::UNTHROTTLED_ON_PURPOSE, true) || str_starts_with($route->uri(), 'livewire'))
            ->reject(fn (Route $route): bool => collect($route->gatherMiddleware())->contains(fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, 'throttle:')))
            ->map(fn (Route $route): string => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unthrottled, 'Add a throttle:<name> middleware (config madeeasy.rate_limits) to these routes.');
    }

    /**
     * Service request form: 5 a minute per IP, and another IP is unaffected.
     *
     * @return void
     */
    public function test_service_request_form_is_limited(): void
    {
        $service = Service::factory()->create();
        $url = route('services.request', [$service->category, $service]);

        $this->assert_route_limited('service-requests', fn (): TestResponse => $this->post($url, []));

        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->post($url, [])->assertStatus(302);
    }

    /**
     * "Buy Result Checker": same "create an order" budget as the request form.
     *
     * @return void
     */
    public function test_result_checker_purchase_is_limited(): void
    {
        $this->assert_route_limited('service-requests', fn (): TestResponse => $this->post(route('result-checker.buy'), []));
    }

    /**
     * "Book a consultation": same "create an order" budget as the request form.
     *
     * @return void
     */
    public function test_consultation_booking_is_limited(): void
    {
        $this->assert_route_limited('service-requests', fn (): TestResponse => $this->post(route('book-consultation.book'), []));
    }

    /**
     * "Needs action" reply on a tracked order.
     *
     * @return void
     */
    public function test_needs_action_response_is_limited(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->withStatus(OrderStatus::NeedsAction)->create(['user_id' => $customer->id]);
        $this->actingAs($customer);

        $this->assert_route_limited('order-responses', fn (): TestResponse => $this->post(route('orders.respond', $order), []));
    }

    /**
     * Testimonial submission has its own budget (not shared with replies).
     *
     * @return void
     */
    public function test_testimonial_submission_is_limited(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->withStatus(OrderStatus::Completed)->create(['user_id' => $customer->id]);
        $this->actingAs($customer);

        $this->assert_route_limited('testimonials', fn (): TestResponse => $this->post(route('orders.testimonial.store', $order), []));

        $needs_action = Order::factory()->withStatus(OrderStatus::NeedsAction)->create(['user_id' => $customer->id]);
        $this->assertNotSame(429, $this->post(route('orders.respond', $needs_action), [])->status());
    }

    /**
     * "Pay now" and the Paystack return page share one payment budget.
     *
     * @return void
     */
    public function test_payment_steps_are_limited(): void
    {
        $order = Order::factory()->guest()->create();

        $this->assert_route_limited('order-payments', fn (): TestResponse => $this->post(route('orders.pay', $order)));

        $this->get(route('orders.payment.return', $order))->assertTooManyRequests();
    }

    /**
     * Course & cut-off search: 60 a minute per IP.
     *
     * @return void
     */
    public function test_course_search_is_limited(): void
    {
        $this->assert_route_limited('course-search', fn (): TestResponse => $this->get(route('admission.search')));
    }

    /**
     * My Requests claim / dismiss / restore: per signed-in user.
     *
     * @return void
     */
    public function test_order_claims_are_limited(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assert_route_limited('account-claims', fn (): TestResponse => $this->post(route('my-requests.claim')));

        $this->actingAs(User::factory()->create());
        $this->assertNotSame(429, $this->post(route('my-requests.claim'))->status());
    }

    /**
     * Email verification link.
     *
     * @return void
     */
    public function test_verification_link_is_limited(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);

        $this->assert_route_limited('verification-links', fn (): TestResponse => $this->get($url));
    }

    /**
     * Sign-in: failed attempts per email + IP; once locked, even the right
     * password is refused until the window passes.
     *
     * @return void
     */
    public function test_login_is_limited(): void
    {
        $user = User::factory()->create();

        $this->assert_livewire_limited(
            'logins',
            fn (): Testable => Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'wrong-password')->call('login'),
            'form.email',
        );

        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login')
            ->assertHasErrors('form.email');
        $this->assertGuest();
    }

    /**
     * Registration: per IP.
     *
     * @return void
     */
    public function test_registration_is_limited(): void
    {
        $this->assert_livewire_limited('registrations', fn (): Testable => Volt::test('pages.auth.register')->call('register'), 'email');
    }

    /**
     * "Forgot password" (sends an email): per IP, whatever address is typed.
     *
     * @return void
     */
    public function test_password_reset_link_is_limited(): void
    {
        $attempt = 0;

        $this->assert_livewire_limited(
            'password-reset-links',
            function () use (&$attempt): Testable {
                $attempt++;

                return Volt::test('pages.auth.forgot-password')->set('email', "nobody{$attempt}@example.com")->call('sendPasswordResetLink');
            },
            'email',
        );
    }

    /**
     * Choosing a new password with a reset token: per IP.
     *
     * @return void
     */
    public function test_password_reset_is_limited(): void
    {
        $this->assert_livewire_limited(
            'password-resets',
            fn (): Testable => Volt::test('pages.auth.reset-password', ['token' => 'not-a-real-token'])
                ->set('email', 'someone@example.com')->set('password', 'new-password-123')->set('password_confirmation', 'new-password-123')
                ->call('resetPassword'),
            'email',
        );
    }

    /**
     * "Resend verification email": per user, and no email once limited.
     *
     * @return void
     */
    public function test_verification_email_resend_is_limited(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        $this->assert_livewire_limited('verification-emails', fn (): Testable => Volt::test('pages.auth.verify-email')->call('sendVerification'), 'verification');

        Notification::assertSentToTimes($user, VerifyEmail::class, FormThrottle::limit('verification-emails')[0]);
    }

    /**
     * "Confirm password": per user.
     *
     * @return void
     */
    public function test_password_confirmation_is_limited(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assert_livewire_limited(
            'password-checks',
            fn (): Testable => Volt::test('pages.auth.confirm-password')->set('password', 'wrong-password')->call('confirmPassword'),
            'password',
        );
    }

    /**
     * "Update password" (checks the current one): per user.
     *
     * @return void
     */
    public function test_password_update_is_limited(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assert_livewire_limited(
            'password-checks',
            fn (): Testable => Volt::test('profile.update-password-form')
                ->set('current_password', 'wrong-password')->set('password', 'new-password-123')->set('password_confirmation', 'new-password-123')
                ->call('updatePassword'),
            'current_password',
        );
    }

    /**
     * The configured number of requests get through; the next one is a 429.
     *
     * @param  string  $limit  Key of config('madeeasy.rate_limits').
     * @param  Closure(): TestResponse  $send
     * @return void
     */
    private function assert_route_limited(string $limit, Closure $send): void
    {
        [$attempts] = FormThrottle::limit($limit);

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $this->assertNotSame(429, $send()->status(), "[{$limit}] attempt {$attempt} of {$attempts} was refused.");
        }

        $send()->assertTooManyRequests();
    }

    /**
     * The configured number of attempts are processed; the next one fails
     * with the "Too many attempts" message on $field.
     *
     * @param  string  $limit  Key of config('madeeasy.rate_limits').
     * @param  Closure(): Testable  $attempt
     * @param  string  $field
     * @return void
     */
    private function assert_livewire_limited(string $limit, Closure $attempt, string $field): void
    {
        [$attempts] = FormThrottle::limit($limit);
        $throttled = fn (Testable $component): bool => str_contains((string) $component->errors()->first($field), 'Too many');

        for ($number = 1; $number <= $attempts; $number++) {
            $this->assertFalse($throttled($attempt()), "[{$limit}] attempt {$number} of {$attempts} was refused.");
        }

        $this->assertTrue($throttled($attempt()), "[{$limit}] attempt ".($attempts + 1).' was not refused.');
    }
}
