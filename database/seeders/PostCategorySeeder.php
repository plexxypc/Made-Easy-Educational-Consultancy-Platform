<?php

namespace Database\Seeders;

use App\Models\PostCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Updates categories from the requirements PDF, section 10.
 *
 * Idempotent: matched on slug and only created if missing, so re-seeding
 * never overwrites a name an admin has changed.
 */
class PostCategorySeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    public const CATEGORIES = [
        'JAMB News',
        'WAEC Updates',
        'NECO Updates',
        'Admission Deadlines',
        'Post-UTME Announcements',
        'University Admission Lists',
        'CAPS Updates',
        'Course Requirements',
        'Cut-Off Mark Information',
        'Scholarship Opportunities',
        'Registration Deadlines',
        'NIN/JAMB Information',
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            // "NIN/JAMB Information" -> "nin-jamb-information".
            PostCategory::query()->firstOrCreate(['slug' => Str::slug(str_replace('/', ' ', $name))], ['name' => $name]);
        }
    }
}
