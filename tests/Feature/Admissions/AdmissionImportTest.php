<?php

namespace Tests\Feature\Admissions;

use App\Actions\Admissions\ImportAdmissionRequirements;
use App\Enums\InstitutionOwnership;
use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Exceptions\AdmissionImportRejected;
use App\Filament\Resources\AdmissionRequirements\AdmissionRequirementResource;
use App\Filament\Resources\AdmissionRequirements\Pages\ManageAdmissionRequirements;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\ManageCourses;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\Institutions\Pages\ManageInstitutions;
use App\Models\AdmissionRequirement;
use App\Models\Course;
use App\Models\Institution;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admission requirements CSV import (all or nothing, problems by line,
 * institutions/courses matched by name) and back-office access.
 */
class AdmissionImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var User
     */
    private User $staff_user;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->staff_user = User::factory()->staff()->create();
    }

    /**
     * A clean file creates institutions, courses and requirements; quoted
     * names with commas, any column order, header aliases and a BOM all work.
     *
     * @return void
     */
    public function test_imports_a_valid_file(): void
    {
        $csv = "\xEF\xBB\xBFCourse,Institution,Cut-off mark,O'Level credits required,State,Ownership,Type\r\n"
            ."Computer Science,University of Lagos,250,5,Lagos,Federal,University\r\n"
            ."Law,\"University of Nigeria, Nsukka\",260,5,Enugu,federal,university\r\n"
            ."\r\n"
            ."Law,University of Lagos,280,5,,,\r\n"
            ."Accounting,Yaba College of Technology,140,4,lagos state,Federal,Polytechnic\r\n";

        $result = $this->import($csv);

        $this->assertSame(['created' => 4, 'updated' => 0, 'unchanged' => 0, 'institutions_created' => 3, 'courses_created' => 3, 'institutions_missing_details' => []], $result);

        $unn = Institution::query()->where('name', 'University of Nigeria, Nsukka')->sole();
        $this->assertSame([InstitutionType::University, InstitutionOwnership::Federal, NigerianState::Enugu], [$unn->type, $unn->ownership, $unn->state]);
        $this->assertSame(NigerianState::Lagos, Institution::query()->where('name', 'Yaba College of Technology')->value('state'));

        $law_unilag = AdmissionRequirement::query()
            ->whereRelation('institution', 'name', 'University of Lagos')
            ->whereRelation('course', 'name', 'Law')
            ->sole();
        $this->assertSame([280, 5], [$law_unilag->cut_off_mark, $law_unilag->olevel_credits_required]);
    }

    /**
     * Existing institutions and courses are matched by name (case and spacing
     * ignored) instead of duplicated; an existing pair is updated, not repeated.
     *
     * @return void
     */
    public function test_matches_existing_institutions_and_courses_by_name(): void
    {
        $unilag = Institution::factory()->create(['name' => 'University of Lagos', 'state' => NigerianState::Lagos, 'ownership' => InstitutionOwnership::Federal]);
        $computer_science = Course::factory()->create(['name' => 'Computer Science']);
        AdmissionRequirement::factory()->for($unilag)->for($computer_science)->create(['cut_off_mark' => 240, 'olevel_credits_required' => 5]);
        $law = Course::factory()->create(['name' => 'Law']);
        AdmissionRequirement::factory()->for($unilag)->for($law)->create(['cut_off_mark' => 270, 'olevel_credits_required' => 5]);

        $result = $this->import("institution,course,cut_off_mark,olevel_credits_required\n"
            ."UNIVERSITY OF LAGOS,computer   science,255,5\n"
            ."  university of lagos ,LAW,270,5\n"
            ."University  of Lagos,Medicine and Surgery,290,5\n");

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['unchanged']);
        $this->assertSame(0, $result['institutions_created']);
        $this->assertSame(1, $result['courses_created']);

        $this->assertSame(1, Institution::query()->count());
        $this->assertSame(3, Course::query()->count());
        $this->assertSame(1, Course::query()->where('name_key', 'computer science')->count());
        $this->assertSame(3, AdmissionRequirement::query()->count());
        $this->assertSame(255, AdmissionRequirement::query()->whereBelongsTo($computer_science)->value('cut_off_mark'));
        $this->assertSame('University of Lagos', $unilag->refresh()->name, 'The stored name is kept as it was.');
    }

    /**
     * Any bad line rejects the whole file: nothing is saved, not even the
     * good lines or new institutions, and each problem names its line.
     *
     * @return void
     */
    public function test_bad_rows_reject_the_whole_batch_with_line_numbers(): void
    {
        $existing = AdmissionRequirement::factory()->create(['cut_off_mark' => 200]);

        $csv = "institution,course,cut_off_mark,olevel_credits_required,type,ownership,state\n"
            ."{$existing->institution->name},{$existing->course->name},210,5,,,\n"  // 2: fine (would update)
            ."New University,Law,250,5,University,Federal,Lagos\n"                 // 3: fine (would create)
            .",Law,250,5,,,\n"                                                     // 4: no institution
            ."Another University,Law,abc,5,,,\n"                                   // 5: cut-off not a number
            ."Another University,Physics,401,10,,,\n"                              // 6: cut-off and credits out of range
            ."University of Nigeria, Nsukka,Law,250,5,,,\n"                        // 7: unquoted comma
            ."Third University,Law,250,5,University,Federal,Atlantis\n"            // 8: unknown state
            ."new university,LAW,240,5,,,\n"                                       // 9: duplicate of line 3
            ."New University,Physics,240,5,,State,\n";                             // 10: contradicts line 3's ownership

        try {
            $this->import($csv);
            $this->fail('The batch should have been rejected.');
        } catch (AdmissionImportRejected $exception) {
            $this->assertSame('Nothing was imported: 7 lines need fixing.', $exception->getMessage());
            $this->assertSame(
                ['Line 4:', 'Line 5:', 'Line 6:', 'Line 7:', 'Line 8:', 'Line 9:', 'Line 10:'],
                array_map(fn (string $problem): string => strtok($problem, ' ').' '.strtok(' '), $exception->problems),
            );
            $this->assertStringContainsString('the institution name is missing', $exception->problems[0]);
            $this->assertStringContainsString('cut-off mark must be a whole number from 1 to 400 (found "abc")', $exception->problems[1]);
            $this->assertStringContainsString('cut-off mark', $exception->problems[2]);
            $this->assertStringContainsString("O'Level credits must be a whole number from 1 to 9", $exception->problems[2]);
            $this->assertStringContainsString('double quotes', $exception->problems[3]);
            $this->assertStringContainsString('unknown state "Atlantis"', $exception->problems[4]);
            $this->assertStringContainsString('is already on line 3', $exception->problems[5]);
            $this->assertStringContainsString('ownership State differs from line 3 (Federal)', $exception->problems[6]);
        }

        $this->assertSame(1, AdmissionRequirement::query()->count());
        $this->assertSame(200, $existing->refresh()->cut_off_mark);
        $this->assertSame(1, Institution::query()->count());
        $this->assertSame(1, Course::query()->count());
    }

    /**
     * Header problems (missing or unknown columns) and details that
     * contradict a stored institution reject the file too.
     *
     * @return void
     */
    public function test_rejects_bad_headers_and_contradicting_institution_details(): void
    {
        $this->assertRejected("institution,course,cut_off\nUniversity of Lagos,Law,250", 'Missing: olevel_credits_required');
        $this->assertRejected("institution,course,cut_off_mark,olevel_credits_required,stat\nUniversity of Lagos,Law,250,5,Lagos", 'unknown column "stat"');
        $this->assertRejected("University of Lagos,Law,250,5\n", 'the first line must be a header row');
        $this->assertRejected("\n\n", 'No rows found');

        Institution::factory()->create(['name' => 'University of Lagos', 'state' => NigerianState::Lagos]);
        $this->assertRejected("institution,course,cut_off_mark,olevel_credits_required,state\nUniversity of Lagos,Law,250,5,Oyo", 'University of Lagos is recorded with state Lagos, but this line says Oyo');

        $this->assertSame(0, AdmissionRequirement::query()->count());
    }

    /**
     * New institutions without details are created but reported; blank
     * details on an existing institution are filled in from the file.
     *
     * @return void
     */
    public function test_reports_and_fills_in_missing_institution_details(): void
    {
        $result = $this->import("institution,course,cut_off_mark,olevel_credits_required\nKwara State Polytechnic,Accounting,120,4\n");

        $this->assertSame(['Kwara State Polytechnic'], $result['institutions_missing_details']);
        $institution = Institution::query()->sole();
        $this->assertTrue($institution->isMissingDetails());

        $result = $this->import("institution,course,cut_off_mark,olevel_credits_required,type,ownership,state\nkwara state polytechnic,Accounting,125,4,Polytechnic,State,Kwara\n");

        $this->assertSame([], $result['institutions_missing_details']);
        $this->assertSame(1, $result['updated']);
        $this->assertFalse($institution->refresh()->isMissingDetails());
        $this->assertSame(NigerianState::Kwara, $institution->state);
    }

    /**
     * Customers can't import, even by calling the action directly.
     *
     * @return void
     */
    public function test_only_staff_and_admins_can_import(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ImportAdmissionRequirements::class)->handle("institution,course,cut_off_mark,olevel_credits_required\nX,Y,200,5", User::factory()->create());
    }

    /**
     * The Filament "Import CSV" action imports an uploaded file and reports a
     * rejected one without saving anything; the template downloads.
     *
     * @return void
     */
    public function test_filament_import_action(): void
    {
        $this->actingAs($this->staff_user);

        Livewire::test(ManageAdmissionRequirements::class)
            ->callAction('importCsv', ['csv' => UploadedFile::fake()->createWithContent('requirements.csv', ImportAdmissionRequirements::template())])
            ->assertHasNoActionErrors()
            ->assertNotified('Import complete');

        $this->assertSame(2, AdmissionRequirement::query()->count());

        Livewire::test(ManageAdmissionRequirements::class)
            ->callAction('importCsv', ['csv' => UploadedFile::fake()->createWithContent('requirements.csv', "institution,course,cut_off_mark,olevel_credits_required\nA,B,999,5\nC,D,200,5\n")])
            ->assertNotified('Nothing was imported: 1 line needs fixing.');

        $this->assertSame(2, AdmissionRequirement::query()->count());
        $this->assertFalse(Institution::query()->where('name', 'C')->exists());

        Livewire::test(ManageAdmissionRequirements::class)
            ->callAction('downloadTemplate')
            ->assertFileDownloaded('admission-requirements-template.csv');
    }

    /**
     * Guests are sent to log in and customers get 403 on every screen; staff
     * and admins get in. Staff can't bulk delete; admins can.
     *
     * @return void
     */
    public function test_only_staff_and_admins_reach_the_crud_and_import_screens(): void
    {
        $requirement = AdmissionRequirement::factory()->create();
        $urls = [
            AdmissionRequirementResource::getUrl('index'),
            InstitutionResource::getUrl('index'),
            CourseResource::getUrl('index'),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->actingAs(User::factory()->create());

        foreach ($urls as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->actingAs($this->staff_user);

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }

        Livewire::test(ManageAdmissionRequirements::class)
            ->assertActionVisible('importCsv')
            ->assertActionVisible('downloadTemplate')
            ->assertCanSeeTableRecords([$requirement])
            ->assertActionHidden(TestAction::make('delete')->table()->bulk());
        Livewire::test(ManageInstitutions::class)
            ->assertActionHidden(TestAction::make('delete')->table($requirement->institution));
        Livewire::test(ManageCourses::class)
            ->assertActionHidden(TestAction::make('delete')->table($requirement->course));

        $this->actingAs(User::factory()->admin()->create());
        $this->get(AdmissionRequirementResource::getUrl('index'))->assertOk();
        Livewire::test(ManageAdmissionRequirements::class)
            ->assertActionVisible(TestAction::make('delete')->table()->bulk());
    }

    /**
     * Staff can add and edit single rows; names stay unique ignoring case,
     * and a second row for the same institution + course is refused.
     *
     * @return void
     */
    public function test_crud_screens_create_and_validate(): void
    {
        $this->actingAs($this->staff_user);

        Livewire::test(ManageInstitutions::class)
            ->callAction('create', ['name' => 'University of Ilorin', 'type' => 'university', 'ownership' => 'federal', 'state' => 'kwara'])
            ->assertHasNoActionErrors();
        Livewire::test(ManageInstitutions::class)
            ->callAction('create', ['name' => 'university of  ILORIN', 'type' => 'university', 'ownership' => 'federal', 'state' => 'kwara'])
            ->assertHasActionErrors(['name']);
        Livewire::test(ManageCourses::class)
            ->callAction('create', ['name' => 'Pharmacy'])
            ->assertHasNoActionErrors();

        $institution = Institution::query()->sole();
        $course = Course::query()->sole();
        $this->assertSame('university of ilorin', $institution->name_key);

        Livewire::test(ManageAdmissionRequirements::class)
            ->callAction('create', ['institution_id' => $institution->id, 'course_id' => $course->id, 'cut_off_mark' => 250, 'olevel_credits_required' => 5])
            ->assertHasNoActionErrors();
        Livewire::test(ManageAdmissionRequirements::class)
            ->callAction('create', ['institution_id' => $institution->id, 'course_id' => $course->id, 'cut_off_mark' => 260, 'olevel_credits_required' => 5])
            ->assertHasActionErrors(['course_id' => 'unique']);
        Livewire::test(ManageAdmissionRequirements::class)
            ->callAction('create', ['institution_id' => $institution->id, 'course_id' => Course::factory()->create()->id, 'cut_off_mark' => 401, 'olevel_credits_required' => 10])
            ->assertHasActionErrors(['cut_off_mark', 'olevel_credits_required']);

        $this->assertSame(1, AdmissionRequirement::query()->count());
    }

    /**
     * An alias ("UNILAG") updates the existing institution instead of
     * creating a duplicate, and the alias and real name in one file count as
     * the same institution (so the same course twice is caught).
     *
     * @return void
     */
    public function test_institution_aliases_match_on_import(): void
    {
        $unilag = Institution::factory()->create(['name' => 'University of Lagos', 'aliases' => [' unilag ', 'UNILAG', 'University of Lagos', '']]);
        $this->assertSame(['unilag'], $unilag->refresh()->aliases);

        $result = $this->import("institution,course,cut_off_mark,olevel_credits_required\nUnilag,Law,280,5\nUniversity of Lagos,Medicine,290,5");

        $this->assertSame(0, $result['institutions_created']);
        $this->assertSame(1, Institution::query()->count());
        $this->assertSame(2, $unilag->admissionRequirements()->count());

        $this->assertRejected(
            "institution,course,cut_off_mark,olevel_credits_required\nUNILAG,Law,281,5\nuniversity of lagos,Law,282,5",
            'Line 3:',
        );
        $this->assertSame(280, $unilag->admissionRequirements()->whereHas('course', fn ($query) => $query->where('name', 'Law'))->sole()->cut_off_mark);
    }

    /**
     * The admin form refuses an alias another institution already uses as
     * its name or alias, and a name that is another institution's alias.
     *
     * @return void
     */
    public function test_aliases_cannot_collide_in_the_admin_form(): void
    {
        $this->actingAs($this->staff_user);
        $unilag = Institution::factory()->create(['name' => 'University of Lagos', 'aliases' => ['UNILAG']]);
        $lasu = Institution::factory()->create(['name' => 'Lagos State University']);
        $details = ['type' => 'university', 'ownership' => 'state', 'state' => 'lagos'];

        foreach ([['unilag'], ['University of LAGOS']] as $aliases) {
            Livewire::test(ManageInstitutions::class)
                ->callAction(TestAction::make('edit')->table($lasu), ['name' => 'Lagos State University', 'aliases' => $aliases] + $details)
                ->assertHasActionErrors(['aliases']);
        }

        Livewire::test(ManageInstitutions::class)
            ->callAction('create', ['name' => 'Unilag'] + $details)
            ->assertHasActionErrors(['name']);

        Livewire::test(ManageInstitutions::class)
            ->callAction(TestAction::make('edit')->table($lasu), ['name' => 'Lagos State University', 'aliases' => ['LASU']] + $details)
            ->assertHasNoActionErrors();
        Livewire::test(ManageInstitutions::class)
            ->callAction(TestAction::make('edit')->table($unilag), ['name' => 'University of Lagos', 'aliases' => ['UNILAG', 'Unilag Akoka']] + $details)
            ->assertHasNoActionErrors();

        $this->assertSame(['LASU'], $lasu->refresh()->aliases);
        $this->assertSame(['UNILAG', 'Unilag Akoka'], $unilag->refresh()->aliases);
    }

    /**
     * @param  string  $csv
     * @return array<string, mixed>
     */
    private function import(string $csv): array
    {
        return app(ImportAdmissionRequirements::class)->handle($csv, $this->staff_user);
    }

    /**
     * @param  string  $csv
     * @param  string  $expected_text  Expected in the message or the first problem.
     * @return void
     */
    private function assertRejected(string $csv, string $expected_text): void
    {
        try {
            $this->import($csv);
            $this->fail('The file should have been rejected.');
        } catch (AdmissionImportRejected $exception) {
            $this->assertStringContainsString($expected_text, $exception->getMessage().' '.implode(' ', $exception->problems));
        }
    }
}
