<?php

namespace Database\Seeders;

use App\Enums\InstitutionOwnership as Ownership;
use App\Enums\InstitutionType as Type;
use App\Enums\NigerianState as State;
use App\Models\AdmissionRequirement;
use App\Models\Course;
use App\Models\Institution;
use Illuminate\Database\Seeder;

/**
 * SAMPLE DATA for the course & cut-off search: real institution names with
 * ILLUSTRATIVE cut-off marks and credit counts, so the feature can be shown
 * working. The numbers are NOT official and must be replaced with the
 * client's dataset (CSV import) before go-live; see the README.
 *
 * Safe to re-run (matches by name). DatabaseSeeder skips it in production;
 * run it there only on purpose: php artisan db:seed --class=AdmissionSampleDataSeeder
 */
class AdmissionSampleDataSeeder extends Seeder
{
    /**
     * name => [type, ownership, state].
     *
     * @var array<string, array{0: Type, 1: Ownership, 2: State}>
     */
    private const INSTITUTIONS = [
        'University of Lagos' => [Type::University, Ownership::Federal, State::Lagos],
        'University of Ibadan' => [Type::University, Ownership::Federal, State::Oyo],
        'Obafemi Awolowo University' => [Type::University, Ownership::Federal, State::Osun],
        'University of Nigeria, Nsukka' => [Type::University, Ownership::Federal, State::Enugu],
        'Ahmadu Bello University' => [Type::University, Ownership::Federal, State::Kaduna],
        'Lagos State University' => [Type::University, Ownership::State, State::Lagos],
        'Covenant University' => [Type::University, Ownership::Private, State::Ogun],
        'Yaba College of Technology' => [Type::Polytechnic, Ownership::Federal, State::Lagos],
        'Federal Polytechnic, Ilaro' => [Type::Polytechnic, Ownership::Federal, State::Ogun],
        'Federal College of Education (Technical), Akoka' => [Type::CollegeOfEducation, Ownership::Federal, State::Lagos],
        'Adeniran Ogunsanya College of Education' => [Type::CollegeOfEducation, Ownership::State, State::Lagos],
    ];

    /**
     * [institution, course, cut-off mark, O'Level credits]. Illustrative only.
     *
     * @var array<int, array{0: string, 1: string, 2: int, 3: int}>
     */
    private const REQUIREMENTS = [
        ['University of Lagos', 'Medicine and Surgery', 290, 5],
        ['University of Lagos', 'Law', 280, 5],
        ['University of Lagos', 'Computer Science', 260, 5],
        ['University of Lagos', 'Accounting', 250, 5],
        ['University of Lagos', 'Mass Communication', 250, 5],
        ['University of Lagos', 'Economics', 240, 5],
        ['University of Ibadan', 'Medicine and Surgery', 290, 5],
        ['University of Ibadan', 'Law', 270, 5],
        ['University of Ibadan', 'Computer Science', 250, 5],
        ['University of Ibadan', 'Economics', 240, 5],
        ['University of Ibadan', 'Nursing Science', 260, 5],
        ['Obafemi Awolowo University', 'Medicine and Surgery', 285, 5],
        ['Obafemi Awolowo University', 'Law', 265, 5],
        ['Obafemi Awolowo University', 'Computer Science', 250, 5],
        ['Obafemi Awolowo University', 'Accounting', 240, 5],
        ['Obafemi Awolowo University', 'Electrical/Electronic Engineering', 250, 5],
        ['University of Nigeria, Nsukka', 'Medicine and Surgery', 280, 5],
        ['University of Nigeria, Nsukka', 'Law', 260, 5],
        ['University of Nigeria, Nsukka', 'Computer Science', 230, 5],
        ['University of Nigeria, Nsukka', 'Mass Communication', 220, 5],
        ['University of Nigeria, Nsukka', 'Nursing Science', 250, 5],
        ['Ahmadu Bello University', 'Medicine and Surgery', 270, 5],
        ['Ahmadu Bello University', 'Law', 240, 5],
        ['Ahmadu Bello University', 'Computer Science', 220, 5],
        ['Ahmadu Bello University', 'Accounting', 220, 5],
        ['Ahmadu Bello University', 'Electrical/Electronic Engineering', 220, 5],
        ['Lagos State University', 'Medicine and Surgery', 260, 5],
        ['Lagos State University', 'Law', 240, 5],
        ['Lagos State University', 'Computer Science', 220, 5],
        ['Lagos State University', 'Accounting', 210, 5],
        ['Lagos State University', 'Mass Communication', 200, 5],
        ['Covenant University', 'Computer Science', 200, 5],
        ['Covenant University', 'Accounting', 190, 5],
        ['Covenant University', 'Electrical/Electronic Engineering', 200, 5],
        ['Covenant University', 'Mass Communication', 180, 5],
        ['Covenant University', 'Economics', 180, 5],
        ['Yaba College of Technology', 'Computer Science', 150, 5],
        ['Yaba College of Technology', 'Accounting', 140, 5],
        ['Yaba College of Technology', 'Mass Communication', 150, 5],
        ['Yaba College of Technology', 'Electrical/Electronic Engineering', 140, 5],
        ['Yaba College of Technology', 'Business Administration', 140, 4],
        ['Federal Polytechnic, Ilaro', 'Computer Science', 140, 5],
        ['Federal Polytechnic, Ilaro', 'Accounting', 120, 4],
        ['Federal Polytechnic, Ilaro', 'Business Administration', 120, 4],
        ['Federal Polytechnic, Ilaro', 'Mass Communication', 130, 4],
        ['Federal College of Education (Technical), Akoka', 'Computer Science Education', 120, 4],
        ['Federal College of Education (Technical), Akoka', 'Business Education', 100, 4],
        ['Adeniran Ogunsanya College of Education', 'Computer Science Education', 100, 4],
        ['Adeniran Ogunsanya College of Education', 'Business Education', 100, 4],
    ];

    /**
     * Run the database seeds. name_key is set explicitly because seeders may
     * run without model events (DatabaseSeeder uses WithoutModelEvents).
     *
     * @return void
     */
    public function run(): void
    {
        $institution_ids = [];

        foreach (self::INSTITUTIONS as $name => [$type, $ownership, $state]) {
            $institution = Institution::query()->firstOrNew(['name_key' => Institution::name_key($name)]);
            $institution->forceFill(['name' => $name, 'name_key' => Institution::name_key($name), 'type' => $type, 'ownership' => $ownership, 'state' => $state])->save();
            $institution_ids[$name] = $institution->id;
        }

        $course_ids = [];

        foreach (array_unique(array_column(self::REQUIREMENTS, 1)) as $name) {
            $course = Course::query()->firstOrNew(['name_key' => Course::name_key($name)]);
            $course->forceFill(['name' => $name, 'name_key' => Course::name_key($name)])->save();
            $course_ids[$name] = $course->id;
        }

        foreach (self::REQUIREMENTS as [$institution, $course, $cut_off_mark, $credits]) {
            AdmissionRequirement::query()->updateOrCreate(
                ['institution_id' => $institution_ids[$institution], 'course_id' => $course_ids[$course]],
                ['cut_off_mark' => $cut_off_mark, 'olevel_credits_required' => $credits],
            );
        }

        $this->command?->warn('Seeded SAMPLE admission data (illustrative cut-off marks). Replace it with the real dataset before go-live.');
    }
}
