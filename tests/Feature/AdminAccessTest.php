<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Customers are rejected from the admin panel with a clear 403, not a login form.
     *
     * @return void
     */
    public function test_non_staff_user_is_rejected_from_admin_panel(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get('/admin');

        $response->assertForbidden();
        $response->assertSee('You are not authorized to access this area.');
        $response->assertDontSee('Sign in');
    }

    /**
     * Guests are sent to the single app login, and Filament has no login page of its own.
     *
     * @return void
     */
    public function test_guest_is_sent_to_app_login_and_filament_login_is_disabled(): void
    {
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/admin/login')->assertNotFound();
    }

    /**
     * Staff and admin can open the panel.
     *
     * @return void
     */
    public function test_staff_and_admin_can_open_admin_panel(): void
    {
        $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    /**
     * The middleware stacks used for the staff and admin route groups: customers
     * are rejected from both, staff from admin-only.
     *
     * @return void
     */
    public function test_role_gated_route_groups_reject_insufficient_roles(): void
    {
        Route::middleware(['web', 'auth', 'role:staff,admin'])->get('staff/_probe', fn () => 'staff ok');
        Route::middleware(['web', 'auth', 'role:admin'])->get('admin/_probe', fn () => 'admin ok');

        $customer = User::factory()->create();
        $staff_user = User::factory()->staff()->create();

        $this->actingAs($customer)->get('staff/_probe')->assertForbidden();
        $this->actingAs($customer)->get('admin/_probe')->assertForbidden();
        $this->actingAs($staff_user)->get('staff/_probe')->assertOk();
        $this->actingAs($staff_user)->get('admin/_probe')->assertForbidden();
    }

    /**
     * Guard rail for future work: every registered /admin* or /staff* route,
     * including Filament's, must carry the role middleware. Fails if someone adds
     * a route outside routes/staff.php / routes/admin.php without a gate.
     *
     * @return void
     */
    public function test_every_admin_and_staff_route_is_role_protected(): void
    {
        $unprotected_routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => Str::is(['admin', 'admin/*', 'staff', 'staff/*'], $route->uri()))
            ->reject(function (RoutingRoute $route): bool {
                $middleware = $route->gatherMiddleware();

                return collect($middleware)->contains(
                    fn (mixed $name): bool => is_string($name)
                        && (Str::startsWith($name, ['role:', EnsureUserHasRole::class.':'])),
                );
            })
            ->map(fn (RoutingRoute $route): string => $route->methods()[0].' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected_routes, 'Unprotected admin/staff routes: '.implode(', ', $unprotected_routes));
    }
}
