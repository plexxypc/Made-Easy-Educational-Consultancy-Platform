<?php

namespace Tests\Feature\Admissions;

use App\Enums\InstitutionOwnership;
use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Models\AdmissionRequirement;
use App\Models\Course;
use App\Models\Institution;
use App\Models\ServiceCategory;
use App\Support\PublicNavigation;
use Database\Seeders\AdmissionSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Public course & cut-off search: every condition must hold, blank fields
 * broaden the search, and anything below a requirement is left out.
 */
class AdmissionSearchTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement ids by label, for readable assertions.
     *
     * @var array<string, int>
     */
    private array $ids = [];

    /**
     * A small grid where each row fails exactly one condition of the
     * "score 250, 5 credits, Computer Science, Lagos" search:
     *  - unilag_cs:   passes everything (cut-off 250 = the score);
     *  - lasu_cs:     passes everything;
     *  - unilag_med:  wrong course;
     *  - ui_cs:       wrong state (Oyo);
     *  - yabatech_cs: needs 6 credits;
     *  - covenant_cs: cut-off 251, one above the score (Ogun, but also tests the score).
     *  - lagos_high:  Lagos, Computer Science, cut-off 280 (score too low).
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $unilag = $this->institution('University of Lagos', NigerianState::Lagos);
        $lasu = $this->institution('Lagos State University', NigerianState::Lagos, InstitutionOwnership::State);
        $ui = $this->institution('University of Ibadan', NigerianState::Oyo);
        $yabatech = $this->institution('Yaba College of Technology', NigerianState::Lagos, type: InstitutionType::Polytechnic);
        $covenant = $this->institution('Covenant University', NigerianState::Lagos, InstitutionOwnership::Private);
        $pan_atlantic = $this->institution('Pan-Atlantic University', NigerianState::Lagos, InstitutionOwnership::Private);

        $computer_science = Course::factory()->create(['name' => 'Computer Science']);
        $medicine = Course::factory()->create(['name' => 'Medicine and Surgery']);

        $this->ids = [
            'unilag_cs' => $this->requirement($unilag, $computer_science, 250, 5),
            'lasu_cs' => $this->requirement($lasu, $computer_science, 200, 4),
            'unilag_med' => $this->requirement($unilag, $medicine, 240, 5),
            'ui_cs' => $this->requirement($ui, $computer_science, 230, 5),
            'yabatech_cs' => $this->requirement($yabatech, $computer_science, 150, 6),
            'covenant_cs' => $this->requirement($covenant, $computer_science, 251, 5),
            'lagos_high' => $this->requirement($pan_atlantic, $computer_science, 280, 5),
        ];
    }

    /**
     * All four fields: only rows meeting score, credits, course AND state.
     *
     * @return void
     */
    public function test_all_four_fields_return_only_rows_meeting_every_condition(): void
    {
        $response = $this->search(['score' => 250, 'credits' => 5, 'course' => 'Computer Science', 'state' => 'lagos']);

        $this->assertSame(['unilag_cs', 'lasu_cs'], $this->labels($response));
        $response->assertSee('2 matches')
            ->assertSee('Your score: 250')
            ->assertSee('You have: 5');
    }

    /**
     * A score below the cut-off, or fewer credits than required, excludes the row.
     *
     * @return void
     */
    public function test_score_or_credits_below_the_requirement_exclude_the_result(): void
    {
        $this->assertSame(['unilag_cs', 'ui_cs', 'lasu_cs', 'yabatech_cs'], $this->labels($this->search(['score' => 250, 'course' => 'computer science'])));
        $this->assertNotContains('covenant_cs', $this->labels($this->search(['score' => 250])));
        $this->assertContains('covenant_cs', $this->labels($this->search(['score' => 251])));

        $this->assertNotContains('yabatech_cs', $this->labels($this->search(['credits' => 5])));
        $this->assertContains('yabatech_cs', $this->labels($this->search(['credits' => 6])));

        $this->assertSame([], $this->labels($this->search(['score' => 149])));
        $this->assertSame(['lasu_cs'], $this->labels($this->search(['credits' => 4])));
    }

    /**
     * Blank fields widen the search instead of returning nothing; an
     * all-blank submit lists everything.
     *
     * @return void
     */
    public function test_blank_fields_broaden_the_results(): void
    {
        $everything = $this->labels($this->search(['score' => '', 'credits' => '', 'course' => '', 'state' => '']));
        $this->assertCount(7, $everything);
        $this->assertSame('lagos_high', $everything[0], 'Highest cut-off first.');

        $course_only = $this->labels($this->search(['course' => 'Computer Science']));
        $this->assertCount(6, $course_only);
        $this->assertNotContains('unilag_med', $course_only);

        $course_and_state = $this->labels($this->search(['course' => 'Computer Science', 'state' => 'lagos']));
        $this->assertCount(5, $course_and_state);
        $this->assertNotContains('ui_cs', $course_and_state);

        $this->assertSame(['ui_cs'], $this->labels($this->search(['state' => 'oyo'])));
    }

    /**
     * Course text matches any part of the name, ignoring case and spacing;
     * a state filter leaves out institutions with no recorded state.
     *
     * @return void
     */
    public function test_course_text_is_a_partial_case_insensitive_match(): void
    {
        $this->assertSame(['unilag_med'], $this->labels($this->search(['course' => '  MEDICINE '])));
        $this->assertCount(6, $this->labels($this->search(['course' => 'computer'])));
        $this->assertSame([], $this->labels($this->search(['course' => 'Law'])));
        $this->assertSame([], $this->labels($this->search(['course' => 'comp%science'])), 'A typed % is not a LIKE wildcard.');

        $no_state = Institution::factory()->create(['name' => 'Imported Polytechnic', 'state' => null]);
        AdmissionRequirement::factory()->for($no_state)->for(Course::query()->where('name', 'Medicine and Surgery')->sole())->create(['cut_off_mark' => 100]);

        $this->assertCount(2, $this->labels($this->search(['course' => 'medicine'])));
        $this->assertSame(['unilag_med'], $this->labels($this->search(['course' => 'medicine', 'state' => 'lagos'])));
    }

    /**
     * Before searching: the form and the Admission Guidance call to action, no
     * results. Bad values go back to the form with a message.
     *
     * @return void
     */
    public function test_form_validation_and_call_to_action(): void
    {
        $this->get(route('admission.search'))
            ->assertOk()
            ->assertSee('Can I get in with my score?')
            ->assertSee('Get Admission Guidance')
            ->assertDontSee('matches')
            ->assertSee('<option value="Computer Science"></option>', false);

        $this->get(route('admission.search', ['score' => 401]))
            ->assertRedirect(route('admission.search'))
            ->assertSessionHasErrors('score');
        $this->get(route('admission.search', ['score' => 'abc', 'state' => 'atlantis']))
            ->assertRedirect(route('admission.search'))
            ->assertSessionHasErrors(['score', 'state']);

        $this->search(['course' => 'Underwater Basket Weaving'])
            ->assertSee('No matches')
            ->assertSee('Try leaving the state or course empty');
    }

    /**
     * The nav's Admission section links to the search, which is highlighted
     * as the current page; the Admission category page links to it too.
     *
     * @return void
     */
    public function test_navigation_links_to_the_search(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Course &amp; cut-off search', false)
            ->assertSee(route('admission.search'));

        $html = $this->get(route('admission.search'))->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('admission.search'), '#').'"\s+aria-current="page"#', $html);

        ServiceCategory::factory()->create(['slug' => PublicNavigation::ADMISSION_CATEGORY_SLUG]);
        $this->get(route('services.category', ['category' => PublicNavigation::ADMISSION_CATEGORY_SLUG]))
            ->assertSee('Search courses and cut-off marks');
    }

    /**
     * The sample seeder loads a working dataset and can be re-run safely.
     *
     * @return void
     */
    public function test_sample_data_seeder_is_searchable_and_rerunnable(): void
    {
        AdmissionRequirement::query()->delete();
        Institution::query()->delete();
        Course::query()->delete();

        $this->seed(AdmissionSampleDataSeeder::class);
        $counts = [Institution::query()->count(), Course::query()->count(), AdmissionRequirement::query()->count()];
        $this->seed(AdmissionSampleDataSeeder::class);

        $this->assertSame($counts, [Institution::query()->count(), Course::query()->count(), AdmissionRequirement::query()->count()]);
        $this->assertGreaterThan(5, $counts[0]);
        $this->assertSame('university of nigeria, nsukka', Institution::query()->where('name', 'University of Nigeria, Nsukka')->value('name_key'));

        $this->search(['score' => 260, 'credits' => 5, 'course' => 'Computer Science', 'state' => 'lagos'])
            ->assertSee('University of Lagos')
            ->assertSee('Yaba College of Technology')
            ->assertDontSee('University of Ibadan');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return TestResponse
     */
    private function search(array $query): TestResponse
    {
        return $this->get(route('admission.search', $query))->assertOk();
    }

    /**
     * Labels of the requirements shown, in page order.
     *
     * @param  TestResponse  $response
     * @return array<int, string>
     */
    private function labels(TestResponse $response): array
    {
        $results = $response->viewData('results');
        $by_id = array_flip($this->ids);

        return collect($results->items())->map(fn (AdmissionRequirement $requirement): string => $by_id[$requirement->id] ?? 'other:'.$requirement->id)->all();
    }

    /**
     * @param  string  $name
     * @param  NigerianState  $state
     * @param  InstitutionOwnership  $ownership
     * @param  InstitutionType  $type
     * @return Institution
     */
    private function institution(string $name, NigerianState $state, InstitutionOwnership $ownership = InstitutionOwnership::Federal, InstitutionType $type = InstitutionType::University): Institution
    {
        return Institution::factory()->create(['name' => $name, 'state' => $state, 'ownership' => $ownership, 'type' => $type]);
    }

    /**
     * @param  Institution  $institution
     * @param  Course  $course
     * @param  int  $cut_off_mark
     * @param  int  $credits
     * @return int
     */
    private function requirement(Institution $institution, Course $course, int $cut_off_mark, int $credits): int
    {
        return AdmissionRequirement::factory()->for($institution)->for($course)->create([
            'cut_off_mark' => $cut_off_mark,
            'olevel_credits_required' => $credits,
        ])->id;
    }
}
