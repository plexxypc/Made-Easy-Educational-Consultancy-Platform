<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder here is safe to re-run.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            ServiceCatalogueSeeder::class,
            PostCategorySeeder::class,
            TestUsersSeeder::class, // skipped in production
        ]);
    }
}
