<?php

namespace Tests\Feature\Account;

use App\Actions\Customers\ClaimGuestOrders;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Adding guest orders to an account: offered only for the account's own
 * verified email, linked only after the customer accepts, and idempotent.
 */
class ClaimGuestOrdersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * New accounts must verify their email (MustVerifyEmail is on).
     *
     * @return void
     */
    public function test_email_verification_is_enabled(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        event(new Registered($user));

        $this->assertInstanceOf(MustVerifyEmail::class, $user);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    /**
     * The prompt lists only guest orders with exactly the account's verified
     * email (letter case aside), and viewing it links nothing.
     *
     * @return void
     */
    public function test_prompt_only_appears_for_a_verified_email_match(): void
    {
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        $exact = $this->guest_order('ada@example.com', 'Exact Match Service');
        $other_case = $this->guest_order(' ADA@Example.com', 'Other Case Service');
        $similar = $this->guest_order('ada@example.co', 'Similar Email Service');
        $plus = $this->guest_order('ada+school@example.com', 'Plus Address Service');
        $same_phone = $this->guest_order('someone@example.com', 'Same Phone Service', phone: '08031234567');
        $someone_elses = Order::factory()->for(User::factory())->for(Service::factory()->state(['name' => 'Account Order Service']))->create(['guest_email' => 'ada@example.com']);
        $deleted = $this->guest_order('ada@example.com', 'Deleted Order Service');
        $deleted->delete();

        $response = $this->actingAs($ada)->get(route('my-requests'))
            ->assertOk()
            ->assertSee('We found 2 requests placed as a guest with your email — add them to your account?')
            ->assertSee('Exact Match Service')
            ->assertSee('Other Case Service')
            ->assertSee('value="'.$exact->id.'"', false)
            ->assertSee('value="'.$other_case->id.'"', false);

        foreach ([$similar, $plus, $same_phone, $someone_elses, $deleted] as $not_offered) {
            $response->assertDontSee($not_offered->service->name)->assertDontSee('value="'.$not_offered->id.'"', false);
        }

        $this->assertNull($exact->refresh()->user_id);
        $this->assertNull($other_case->refresh()->user_id);

        // Unverified (e.g. just changed their email): no prompt, just a nudge to verify.
        $ada->forceFill(['email_verified_at' => null])->save();
        $this->get(route('my-requests'))
            ->assertOk()
            ->assertDontSee('We found')
            ->assertSee('Verify your email address');
        $this->post(route('my-requests.claim'), ['orders' => [$exact->id]])->assertRedirect(route('verification.notice'));
        $this->assertNull($exact->refresh()->user_id);

        $this->expectException(BusinessRuleException::class);
        app(ClaimGuestOrders::class)->handle($ada, [$exact->id]);
    }

    /**
     * Accepting links exactly the matching orders shown, once. Anything else
     * posted (another customer's order, a non-matching guest order, made-up
     * ids) is ignored, and a repeat claim does nothing.
     *
     * @return void
     */
    public function test_claiming_is_explicit_and_idempotent(): void
    {
        $ada = User::factory()->create(['email' => 'ada@example.com']);
        $first = $this->guest_order('ada@example.com', 'First Guest Service');
        $second = $this->guest_order('Ada@Example.com', 'Second Guest Service');
        $not_matching = $this->guest_order('bola@example.com', 'Bola Guest Service');
        $bolas = Order::factory()->for($bola = User::factory()->create(['email' => 'bola@example.com']))->create(['guest_email' => 'ada@example.com']);

        $this->actingAs($ada)
            ->post(route('my-requests.claim'), ['orders' => [$first->id, $second->id, $not_matching->id, $bolas->id, 999_999]])
            ->assertRedirect(route('my-requests'))
            ->assertSessionHas('status', '2 requests have been added to your account.');

        $this->assertTrue($first->refresh()->isOwnedBy($ada));
        $this->assertTrue($second->refresh()->isOwnedBy($ada));
        $this->assertNotNull($first->claimed_at);
        $this->assertNull($not_matching->refresh()->user_id);
        $this->assertTrue($bolas->refresh()->isOwnedBy($bola));

        // Claimed orders behave like account orders: listed, no lookup needed, no prompt.
        $this->get(route('my-requests'))
            ->assertOk()
            ->assertSee('First Guest Service')
            ->assertSee('Second Guest Service')
            ->assertDontSee('We found');
        $this->get(route('orders.track.show', $first))->assertOk();

        // Claiming again changes nothing.
        $claimed_at = $first->claimed_at;
        $this->travel(5)->minutes();
        $this->post(route('my-requests.claim'), ['orders' => [$first->id, $second->id]])
            ->assertRedirect(route('my-requests'))
            ->assertSessionHas('status', 'There was nothing new to add to your account.');
        $this->assertTrue($first->refresh()->claimed_at->equalTo($claimed_at));
        $this->assertSame(0, app(ClaimGuestOrders::class)->handle($ada, [$first->id, $second->id]));
    }

    /**
     * Another customer can't claim someone's guest order, even with its id.
     *
     * @return void
     */
    public function test_cannot_claim_an_order_placed_with_someone_elses_email(): void
    {
        $adas_guest_order = $this->guest_order('ada@example.com', 'Ada Guest Service');
        $bola = User::factory()->create(['email' => 'bola@example.com']);

        $this->actingAs($bola)
            ->post(route('my-requests.claim'), ['orders' => [$adas_guest_order->id]])
            ->assertRedirect(route('my-requests'))
            ->assertSessionHas('status', 'There was nothing new to add to your account.');

        $this->assertNull($adas_guest_order->refresh()->user_id);
        $this->actingAs($bola)->get(route('my-requests'))->assertDontSee('Ada Guest Service');
        $this->get(route('orders.track.show', $adas_guest_order))->assertRedirect(route('orders.track'));

        $this->app['auth']->forgetGuards();
        $this->post(route('my-requests.claim'), ['orders' => [$adas_guest_order->id]])->assertRedirect('/login');
        $this->assertNull($adas_guest_order->refresh()->user_id);
    }

    /**
     * @param  string  $email
     * @param  string  $service_name
     * @param  string|null  $phone
     * @return Order
     */
    private function guest_order(string $email, string $service_name, ?string $phone = null): Order
    {
        return Order::factory()->guest()
            ->for(Service::factory()->state(['name' => $service_name]))
            ->create(['guest_email' => $email, 'guest_phone' => $phone ?? '08030000000']);
    }
}
