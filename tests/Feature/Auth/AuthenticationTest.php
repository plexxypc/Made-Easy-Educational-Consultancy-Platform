<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect('/my-requests');

        $response = $this->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }

    /**
     * Signed-in customers can log out from the public header (My Requests and
     * every public page), which links "My account" to the profile page.
     *
     * @return void
     */
    public function test_customers_can_log_out_from_the_public_header(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('my-requests'))
            ->assertOk()
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('Log out')
            ->assertSee('href="'.route('profile').'"', false);

        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        $this->get(route('home'))->assertDontSee('action="'.route('logout').'"', false);
        $this->post(route('logout'))->assertRedirect(route('login'));
    }
}
