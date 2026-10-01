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
            FaqSeeder::class, // drafted until database/data/faqs_section_13.php is filled in
            TestUsersSeeder::class, // skipped in production
        ]);

        // SAMPLE cut-off marks for the course search; never seeded into production by default.
        if (! app()->isProduction()) {
            $this->call(AdmissionSampleDataSeeder::class);
        }
    }
}
