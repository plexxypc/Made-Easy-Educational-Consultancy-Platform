<?php

namespace Tests\Feature\Website;

use App\Actions\Faqs\InstallOfficialFaqs;
use App\Enums\SiteSettingKey;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\Faqs\Pages\ManageFaqs;
use App\Filament\Resources\SiteSettings\Pages\ManageSiteSettings;
use App\Filament\Resources\SiteSettings\SiteSettingResource;
use App\Models\Faq;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\FaqSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FAQ and Site settings edited in the back office show on the public site
 * on the next page load, and only admins can change them.
 */
class ContentAdminTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin creates, edits and hides FAQs; /faq and the homepage follow at
     * once, with FAQPage structured data on /faq.
     *
     * @return void
     */
    public function test_faq_changes_show_immediately(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageFaqs::class)
            ->callAction('create', ['question' => 'Do you register GCE candidates?', 'answer' => "Yes, for both GCE sittings.\n\nBring your passport photo.", 'is_active' => true])
            ->assertHasNoActionErrors();

        $faq = Faq::query()->sole();

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Do you register GCE candidates?')
            ->assertSee('<p class="whitespace-pre-line">Bring your passport photo.</p>', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"Do you register GCE candidates?"', false);
        $this->get(route('home'))->assertSee('Do you register GCE candidates?');

        Livewire::test(ManageFaqs::class)
            ->callAction(TestAction::make('edit')->table($faq), ['question' => 'Do you register GCE (private) candidates?', 'answer' => 'Yes.', 'is_active' => true])
            ->assertHasNoActionErrors();
        $this->get(route('faq'))->assertSee('Do you register GCE (private) candidates?');

        Livewire::test(ManageFaqs::class)
            ->callAction(TestAction::make('edit')->table($faq), ['question' => 'Do you register GCE (private) candidates?', 'answer' => 'Yes.', 'is_active' => false]);

        $this->get(route('faq'))->assertDontSee('GCE (private)')->assertDontSee('FAQPage');
        $this->get(route('home'))->assertDontSee('GCE (private)');
    }

    /**
     * The seeded 7 questions show in order; staff can read FAQs but not
     * change them; customers can't get in.
     *
     * @return void
     */
    public function test_seeded_faqs_and_access(): void
    {
        $this->seed(FaqSeeder::class);
        $this->seed(FaqSeeder::class);
        $this->assertSame(7, Faq::query()->count());

        $questions = Faq::query()->shown()->pluck('question')->all();
        $this->get(route('faq'))->assertOk()->assertSeeInOrder($questions);

        $this->actingAs(User::factory()->create());
        $this->get(FaqResource::getUrl('index'))->assertForbidden();

        $this->actingAs(User::factory()->staff()->create());
        Livewire::test(ManageFaqs::class)
            ->assertOk()
            ->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('edit')->table(Faq::query()->first()));
    }

    /**
     * Installing the official wording removes untouched drafts, keeps (and
     * reports) a draft an admin edited, leaves admin-added questions alone,
     * and is safe to re-run.
     *
     * @return void
     */
    public function test_official_faqs_replace_untouched_drafts(): void
    {
        $this->seed(FaqSeeder::class);
        [$edited_question] = FaqSeeder::DRAFTED[1];
        Faq::query()->where('question', $edited_question)->update(['answer' => 'Edited by an admin.']);
        Faq::factory()->create(['question' => 'Added by an admin?']);

        $official = array_map(fn (int $number): array => ["Official question {$number}?", "Official answer {$number}."], range(1, 7));

        $result = app(InstallOfficialFaqs::class)->handle($official, FaqSeeder::DRAFTED);

        $this->assertSame(['added' => 7, 'removed' => 6, 'kept_drafts' => [$edited_question]], $result);
        $this->assertSame(
            array_column($official, 0),
            Faq::query()->whereLike('question', 'Official%')->orderBy('sort_order')->pluck('question')->all(),
        );
        $this->assertTrue(Faq::query()->where('question', 'Added by an admin?')->exists());

        $again = app(InstallOfficialFaqs::class)->handle($official, FaqSeeder::DRAFTED);
        $this->assertSame(0, $again['added']);
        $this->assertSame(9, Faq::query()->count());
    }

    /**
     * An incomplete official list is refused, and the drop-in file is either
     * still empty (pending) or holds exactly the seven questions.
     *
     * @return void
     */
    public function test_official_faqs_must_be_complete(): void
    {
        $official_count = count(FaqSeeder::official_faqs());
        $this->assertContains($official_count, [0, InstallOfficialFaqs::OFFICIAL_COUNT], 'database/'.FaqSeeder::OFFICIAL_FILE.' must be empty or hold all 7 questions.');

        $this->expectException(InvalidArgumentException::class);
        app(InstallOfficialFaqs::class)->handle([['Only one?', 'Yes.']], FaqSeeder::DRAFTED);
    }

    /**
     * The trust figure appears once an admin sets it; About Us text changes
     * at once; an emptied setting falls back to its default.
     *
     * @return void
     */
    public function test_site_settings_show_immediately(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('Students Assisted With Admission');
        $this->get(route('about'))->assertSee('Our aim is simple');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ManageSiteSettings::class)->assertOk();
        $this->assertSame(count(SiteSettingKey::cases()), SiteSetting::query()->count());

        $figure = SiteSetting::query()->where('key', SiteSettingKey::StudentsAssisted->value)->sole();
        $about = SiteSetting::query()->where('key', SiteSettingKey::AboutBody->value)->sole();

        Livewire::test(ManageSiteSettings::class)
            ->callAction(TestAction::make('edit')->table($figure), ['value' => 'lots'])
            ->assertHasActionErrors(['value']);
        Livewire::test(ManageSiteSettings::class)
            ->callAction(TestAction::make('edit')->table($figure), ['value' => '1,250+'])
            ->assertHasNoActionErrors();
        Livewire::test(ManageSiteSettings::class)
            ->callAction(TestAction::make('edit')->table($about), ['value' => "Founded in Lagos in 2019.\n\nWe have helped families across Nigeria."])
            ->assertHasNoActionErrors();

        $this->get(route('home'))->assertSee('1,250+')->assertSee('Students Assisted With Admission');
        $this->get(route('about'))
            ->assertSee('<p class="whitespace-pre-line">Founded in Lagos in 2019.</p>', false)
            ->assertSee('We have helped families across Nigeria.')
            ->assertDontSee('Our aim is simple');

        Livewire::test(ManageSiteSettings::class)
            ->callAction(TestAction::make('edit')->table($about), ['value' => '']);
        $this->assertNull($about->refresh()->value);
        $this->get(route('about'))->assertSee('Our aim is simple');
    }

    /**
     * Site settings are admin-only: staff and customers get a 403.
     *
     * @return void
     */
    public function test_site_settings_are_admin_only(): void
    {
        foreach ([User::factory()->staff()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(SiteSettingResource::getUrl('index'))->assertForbidden();
        }

        $this->assertDatabaseCount('site_settings', 0);
    }
}
