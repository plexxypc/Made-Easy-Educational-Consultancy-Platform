<?php

namespace App\Http\Controllers;

use App\Enums\NigerianState;
use App\Http\Requests\SearchAdmissionRequirementsRequest;
use App\Models\AdmissionRequirement;
use App\Models\Course;
use App\Models\Institution;
use App\Support\PublicNavigation;
use Illuminate\View\View;

/**
 * "Can I get in with my score?" (PDF section 11): institutions offering a
 * course whose cut-off mark and O'Level credit requirement the student meets.
 * Informational only; the call to action is the Admission Guidance request.
 * Matching rules: AdmissionRequirement::matching().
 */
class AdmissionSearchController extends Controller
{
    /**
     * Search form, and results once it has been submitted. Results: highest
     * cut-off first (the most competitive options the student still meets),
     * then institution and course name.
     *
     * @param  SearchAdmissionRequirementsRequest  $request
     * @return View
     */
    public function __invoke(SearchAdmissionRequirementsRequest $request): View
    {
        $results = null;

        if ($request->has_searched()) {
            $results = AdmissionRequirement::query()
                ->matching($request->score(), $request->credits(), $request->course_text(), $request->state_choice())
                ->with(['institution', 'course'])
                ->orderByDesc('cut_off_mark')
                ->orderBy(Institution::query()->select('name')->whereColumn('institutions.id', 'admission_requirements.institution_id'))
                ->orderBy(Course::query()->select('name')->whereColumn('courses.id', 'admission_requirements.course_id'))
                ->paginate((int) config('madeeasy.admission_search.per_page'))
                ->withQueryString();
        }

        return view('admission.search', [
            'results' => $results,
            'score' => $request->score(),
            'credits' => $request->credits(),
            'course_text' => $request->course_text(),
            'state' => $request->state_choice(),
            'states' => NigerianState::cases(),
            'course_names' => Course::query()->whereHas('admissionRequirements')->orderBy('name')->pluck('name'),
            'admission_guidance_url' => PublicNavigation::admission_guidance_url(),
        ]);
    }
}
