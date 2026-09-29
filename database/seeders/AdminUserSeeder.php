<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates (or updates) the single admin account used to log in to /admin.
 *
 * Credentials come from config('madeeasy.seed_admin'). Outside production,
 * a missing password falls back to "password" for local testing; in
 * production the seeder aborts instead of creating a guessable admin.
 * Re-running is safe: the user is matched on email.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Local-only fallback password.
     *
     * @var string
     */
    private const LOCAL_DEFAULT_PASSWORD = 'password';

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        /** @var array{name: string, email: string, password: ?string} $admin_config */
        $admin_config = config('madeeasy.seed_admin');

        $password = $admin_config['password'];

        if (blank($password)) {
            if (app()->isProduction()) {
                $this->command?->error('SEED_ADMIN_PASSWORD is not set; refusing to seed an admin in production.');

                return;
            }

            $password = self::LOCAL_DEFAULT_PASSWORD;
        }

        $admin_user = User::query()->firstOrNew(['email' => $admin_config['email']]);
        $admin_user->name = $admin_config['name'];
        $admin_user->password = $password;
        $admin_user->role = UserRole::Admin;
        $admin_user->email_verified_at ??= now();
        $admin_user->save();

        $this->command?->info("Admin user ready: {$admin_user->email}");
    }
}
