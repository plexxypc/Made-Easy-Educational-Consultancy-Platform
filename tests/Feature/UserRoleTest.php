<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * New users default to the customer role, both in memory and in the database.
     *
     * @return void
     */
    public function test_new_users_default_to_customer(): void
    {
        $unsaved_user = new User;
        $this->assertSame(UserRole::Customer, $unsaved_user->role);

        $user = User::create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $this->assertSame(UserRole::Customer, $user->fresh()->role);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'customer']);
    }

    /**
     * Role cannot be set through mass assignment (e.g. a tampered registration form).
     *
     * @return void
     */
    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'role' => 'admin',
        ]);

        $this->assertTrue($user->fresh()->isCustomer());
    }

    /**
     * hasRole accepts enum cases and strings and matches any of them.
     *
     * @return void
     */
    public function test_has_role_helpers(): void
    {
        $staff_user = User::factory()->staff()->create();

        $this->assertTrue($staff_user->hasRole('staff'));
        $this->assertTrue($staff_user->hasRole(UserRole::Staff));
        $this->assertTrue($staff_user->hasRole('admin', 'staff'));
        $this->assertFalse($staff_user->hasRole('admin'));
        $this->assertFalse($staff_user->hasRole('not-a-role'));
        $this->assertTrue($staff_user->isStaff());
        $this->assertTrue($staff_user->isStaffOrAdmin());
        $this->assertFalse($staff_user->isAdmin());
    }

    /**
     * The role middleware allows matching roles and forbids everyone else.
     *
     * @return void
     */
    public function test_role_middleware(): void
    {
        Route::middleware(['web', 'auth', 'role:admin'])->get('/_test/admin-only', fn () => 'ok');

        $this->actingAs(User::factory()->create())->get('/_test/admin-only')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/_test/admin-only')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/_test/admin-only')->assertOk();
    }

    /**
     * The @role Blade directive renders only for matching roles.
     *
     * @return void
     */
    public function test_role_blade_directive(): void
    {
        $template = "@role('admin', 'staff') back-office @else public @endrole";

        $this->assertSame('public', trim(Blade::render($template)));

        $this->actingAs(User::factory()->create());
        $this->assertSame('public', trim(Blade::render($template)));

        $this->actingAs(User::factory()->admin()->create());
        $this->assertSame('back-office', trim(Blade::render($template)));
    }

    /**
     * Only staff and admins can open the Filament panel.
     *
     * @return void
     */
    public function test_admin_panel_access_is_role_gated(): void
    {
        $this->get('/admin')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->get('/admin')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
    }

    /**
     * The admin seeder creates a verified admin and is idempotent.
     *
     * @return void
     */
    public function test_admin_seeder_is_idempotent(): void
    {
        config()->set('madeeasy.seed_admin', [
            'name' => 'Seed Admin',
            'email' => 'seed-admin@example.com',
            'password' => 'a-strong-password',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin_users = User::query()->where('email', 'seed-admin@example.com')->get();

        $this->assertCount(1, $admin_users);
        $this->assertTrue($admin_users->first()->isAdmin());
        $this->assertNotNull($admin_users->first()->email_verified_at);
        $this->assertTrue(Hash::check('a-strong-password', $admin_users->first()->password));
    }

    /**
     * In production the seeder refuses to create an admin without an explicit password.
     *
     * @return void
     */
    public function test_admin_seeder_requires_password_in_production(): void
    {
        config()->set('madeeasy.seed_admin.password', null);
        $this->app['env'] = 'production';

        // Run directly: `db:seed` would prompt for confirmation in production.
        $this->app->make(AdminUserSeeder::class)->run();

        $this->assertDatabaseMissing('users', ['role' => 'admin']);
    }
}
