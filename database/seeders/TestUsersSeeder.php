<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One test user per role for local testing (password: "password").
 * Never runs in production.
 */
class TestUsersSeeder extends Seeder
{
    /**
     * Test accounts: email => [name, role].
     *
     * @var array<string, array{0: string, 1: UserRole}>
     */
    private const TEST_USERS = [
        'staff@madeeasy.test' => ['Test Staff', UserRole::Staff],
        'test-admin@madeeasy.test' => ['Test Admin', UserRole::Admin],
        'customer@madeeasy.test' => ['Test Customer', UserRole::Customer],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipping test users in production.');

            return;
        }

        foreach (self::TEST_USERS as $email => [$name, $role]) {
            $test_user = User::query()->firstOrNew(['email' => $email]);
            $test_user->name = $name;
            $test_user->password = 'password';
            $test_user->role = $role;
            $test_user->email_verified_at ??= now();
            $test_user->save();
        }
    }
}
