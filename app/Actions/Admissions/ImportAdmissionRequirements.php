<?php

namespace App\Actions\Admissions;

use App\Enums\InstitutionOwnership;
use App\Enums\InstitutionType;
use App\Enums\NigerianState;
use App\Exceptions\AdmissionImportRejected;
use App\Models\AdmissionRequirement;
use App\Models\Course;
use App\Models\Institution;
use App\Models\User;
use App\Support\EnumInput;
use BackedEnum;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bulk-load admission requirements from a CSV (staff and admins).
 *
 * Format: a header row, then one institution + course per line.
 *   Required columns: institution, course, cut_off_mark, olevel_credits_required
 *   Optional columns: type, ownership, state (used for institutions)
 * Column order is free; common header spellings are accepted (COLUMN_ALIASES).
 * Unknown columns are rejected rather than ignored, so a typo like "stat"
 * can't silently drop data.
 *
 * Business rules:
 *  - All or nothing, as for PIN stock: if any line has a problem, nothing is
 *    saved and every problem is reported by line number (line 1 = header).
 *  - Institutions and courses are matched by name, ignoring case and extra
 *    spaces (HasNameKey); unknown names are created. An institution is also
 *    matched by any of its aliases ("UNILAG" -> University of Lagos), so a
 *    file mixing the two spellings counts them as the same institution.
 *    Other abbreviations and spelling variants are NOT matched: add them as
 *    aliases first, or they become new institutions. Courses have no aliases.
 *  - An institution + course already in the database is UPDATED with the
 *    new cut-off/credits (e.g. a new admission year). The same pair twice
 *    in one file is a problem.
 *  - type/ownership/state: for a new institution they come from its rows
 *    (all rows must agree) and may be left blank, in which case it is
 *    created without them and listed in the result so someone completes
 *    it. For an existing institution, blank details are filled in; details
 *    that CONTRADICT what is stored are a problem (fix one or the other).
 */
class ImportAdmissionRequirements
{
    /**
     * Header spellings (after EnumInput::key) for each column.
     *
     * @var array<string, array<int, string>>
     */
    private const COLUMN_ALIASES = [
        'institution' => ['institution', 'institution_name', 'school', 'school_name'],
        'course' => ['course', 'course_name', 'programme', 'program'],
        'cut_off_mark' => ['cut_off_mark', 'cut_off', 'cutoff', 'cutoff_mark', 'jamb_cut_off', 'jamb_cut_off_mark'],
        'olevel_credits_required' => ['olevel_credits_required', 'olevel_credits', 'credits_required', 'credits', 'o_level_credits', 'o_level_credits_required'],
        'type' => ['type', 'institution_type'],
        'ownership' => ['ownership', 'owner'],
        'state' => ['state', 'location'],
    ];

    /**
     * @var array<int, string>
     */
    private const REQUIRED_COLUMNS = ['institution', 'course', 'cut_off_mark', 'olevel_credits_required'];

    /**
     * Institution details that may be given per line: column => enum class.
     *
     * @var array<string, class-string<BackedEnum>>
     */
    private const DETAIL_COLUMNS = [
        'type' => InstitutionType::class,
        'ownership' => InstitutionOwnership::class,
        'state' => NigerianState::class,
    ];

    /**
     * Header row for a blank template (the "Download template" action).
     *
     * @return string
     */
    public static function template(): string
    {
        return "institution,course,cut_off_mark,olevel_credits_required,type,ownership,state\n"
            ."University of Lagos,Computer Science,250,5,University,Federal,Lagos\n"
            ."\"University of Nigeria, Nsukka\",Law,260,5,University,Federal,Enugu\n";
    }

    /**
     * @param  string  $input  CSV file contents.
     * @param  User  $actor  Staff or admin doing the import.
     * @return array{created: int, updated: int, unchanged: int, institutions_created: int, courses_created: int, institutions_missing_details: array<int, string>}
     *
     * @throws AuthorizationException If the user may not import.
     * @throws AdmissionImportRejected If anything is wrong (nothing is saved).
     */
    public function handle(string $input, User $actor): array
    {
        Gate::forUser($actor)->authorize('import', AdmissionRequirement::class);

        [$rows, $problems] = self::parse($input);

        if ($rows === [] && $problems === []) {
            throw new AdmissionImportRejected('No rows found. Upload a CSV with a header row and one institution and course per line.');
        }

        $max_lines = (int) config('madeeasy.admission_search.import_max_lines');

        if (count($rows) > $max_lines) {
            throw new AdmissionImportRejected('That file has '.count($rows)." rows; upload at most {$max_lines} at a time.");
        }

        $rows = self::resolve_institution_aliases($rows);
        $institutions = self::existing_by_key(Institution::class, array_column($rows, 'institution_key'));
        $courses = self::existing_by_key(Course::class, array_column($rows, 'course_key'));

        [$institution_details, $detail_problems] = self::institution_details($rows, $institutions);
        $problems = $problems + self::duplicate_pairs($rows) + $detail_problems;

        if ($problems !== []) {
            ksort($problems);

            throw new AdmissionImportRejected(
                trans_choice('Nothing was imported: :count line needs fixing.|Nothing was imported: :count lines need fixing.', count($problems)),
                array_values($problems),
            );
        }

        try {
            $result = DB::transaction(fn (): array => self::apply($rows, $institutions, $courses, $institution_details));
        } catch (UniqueConstraintViolationException) {
            throw new AdmissionImportRejected('Nothing was imported: someone else changed institutions, courses or cut-off marks while you were uploading. Upload the file again.');
        }

        Log::info('Admission requirements imported.', ['user_id' => $actor->id] + array_diff_key($result, ['institutions_missing_details' => true]));

        return $result;
    }

    /**
     * Read the header and data lines.
     *
     * @param  string  $input
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private static function parse(string $input): array
    {
        $lines = preg_split('/\R/u', (string) preg_replace('/^\xEF\xBB\xBF/', '', $input)) ?: [];
        $columns = null;
        $rows = [];
        $problems = [];

        foreach ($lines as $index => $line) {
            $line_number = $index + 1;

            if (trim($line) === '') {
                continue;
            }

            $cells = array_map(fn (?string $cell): string => Str::squish((string) $cell), str_getcsv($line, escape: ''));

            if ($columns === null) {
                [$columns, $header_problem] = self::read_header($cells);

                if ($header_problem !== null) {
                    return [[], [$line_number => "Line {$line_number}: {$header_problem}"]];
                }

                continue;
            }

            if (count($cells) > count($columns)) {
                $problems[$line_number] = "Line {$line_number}: found ".count($cells).' values but the header has '.count($columns).'. Put names that contain a comma in double quotes, e.g. "University of Nigeria, Nsukka".';

                continue;
            }

            $values = [];

            foreach ($columns as $position => $column) {
                $values[$column] = $cells[$position] ?? '';
            }

            [$row, $row_problems] = self::read_row($values);

            if ($row_problems !== []) {
                $problems[$line_number] = "Line {$line_number}: ".implode('; ', $row_problems).'.';

                continue;
            }

            $rows[$line_number] = $row;
        }

        return [$rows, $problems];
    }

    /**
     * Map header cells to column names.
     *
     * @param  array<int, string>  $cells
     * @return array{0: array<int, string>|null, 1: string|null} Columns by position, or a problem.
     */
    private static function read_header(array $cells): array
    {
        $columns = [];
        $unknown = [];

        foreach ($cells as $position => $cell) {
            $key = EnumInput::key($cell);
            $column = collect(self::COLUMN_ALIASES)->search(fn (array $aliases): bool => in_array($key, $aliases, true));

            if ($column === false) {
                if ($cell !== '') {
                    $unknown[] = '"'.$cell.'"';
                }

                continue;
            }

            if (in_array($column, $columns, true)) {
                return [null, "the column \"{$cell}\" appears twice in the header."];
            }

            $columns[$position] = $column;
        }

        $missing = array_diff(self::REQUIRED_COLUMNS, $columns);

        if ($missing !== []) {
            return [null, 'the first line must be a header row with the columns '.implode(', ', self::REQUIRED_COLUMNS)
                .' (optional: type, ownership, state). Missing: '.implode(', ', $missing).'.'];
        }

        if ($unknown !== []) {
            return [null, 'unknown '.Str::plural('column', count($unknown)).' '.implode(', ', $unknown).'. Allowed: '
                .implode(', ', array_keys(self::COLUMN_ALIASES)).'.'];
        }

        return [$columns, null];
    }

    /**
     * Validate one data line.
     *
     * @param  array<string, string>  $values  Cell text by column.
     * @return array{0: array<string, mixed>, 1: array<int, string>} The row and its problems.
     */
    private static function read_row(array $values): array
    {
        $problems = [];

        $institution = $values['institution'];
        $course = $values['course'];

        if ($institution === '') {
            $problems[] = 'the institution name is missing';
        } elseif (mb_strlen($institution) > 255) {
            $problems[] = 'the institution name is longer than 255 characters';
        }

        if ($course === '') {
            $problems[] = 'the course name is missing';
        } elseif (mb_strlen($course) > 255) {
            $problems[] = 'the course name is longer than 255 characters';
        }

        $cut_off_mark = self::whole_number($values['cut_off_mark'], 1, AdmissionRequirement::MAX_SCORE);
        $credits = self::whole_number($values['olevel_credits_required'], 1, AdmissionRequirement::MAX_CREDITS);

        if ($cut_off_mark === null) {
            $problems[] = 'the cut-off mark must be a whole number from 1 to '.AdmissionRequirement::MAX_SCORE.' (found "'.$values['cut_off_mark'].'")';
        }

        if ($credits === null) {
            $problems[] = "the O'Level credits must be a whole number from 1 to ".AdmissionRequirement::MAX_CREDITS.' (found "'.$values['olevel_credits_required'].'")';
        }

        $details = [];

        foreach (self::DETAIL_COLUMNS as $column => $enum) {
            $text = $values[$column] ?? '';

            if ($text === '') {
                continue;
            }

            $case = $enum::from_input($text);

            if ($case === null) {
                $problems[] = "unknown {$column} \"{$text}\" (use ".self::allowed_labels($enum).')';

                continue;
            }

            $details[$column] = $case;
        }

        return [[
            'institution' => $institution,
            'institution_key' => Institution::name_key($institution),
            'course' => $course,
            'course_key' => Course::name_key($course),
            'cut_off_mark' => $cut_off_mark,
            'olevel_credits_required' => $credits,
            'details' => $details,
        ], $problems];
    }

    /**
     * "250" -> 250; anything else (blank, "250.5", "abc", out of range) -> null.
     *
     * @param  string  $text
     * @param  int  $min
     * @param  int  $max
     * @return int|null
     */
    private static function whole_number(string $text, int $min, int $max): ?int
    {
        if (! preg_match('/^\d{1,4}$/', $text)) {
            return null;
        }

        $number = (int) $text;

        return $number >= $min && $number <= $max ? $number : null;
    }

    /**
     * e.g. "Federal, State or Private".
     *
     * @param  class-string<BackedEnum>  $enum
     * @return string
     */
    private static function allowed_labels(string $enum): string
    {
        if ($enum === NigerianState::class) {
            return 'a Nigerian state name, or FCT';
        }

        $labels = array_map(fn (BackedEnum $case): string => (string) $case->getLabel(), $enum::cases());

        return implode(', ', array_slice($labels, 0, -1)).' or '.end($labels);
    }

    /**
     * Existing institutions or courses for these name keys.
     *
     * @template T of Institution|Course
     *
     * @param  class-string<T>  $model
     * @param  array<int, string>  $keys
     * @return array<string, T>
     */
    private static function existing_by_key(string $model, array $keys): array
    {
        $existing = [];

        foreach (array_chunk(array_values(array_unique($keys)), 500) as $chunk) {
            foreach ($model::query()->whereIn('name_key', $chunk)->get() as $record) {
                $existing[$record->name_key] = $record;
            }
        }

        return $existing;
    }

    /**
     * Point lines that use an alias at the institution's real name key, so
     * every later step (duplicates, details, saving) sees one institution.
     * A real name always wins over an alias; the admin form keeps the two
     * from clashing.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private static function resolve_institution_aliases(array $rows): array
    {
        $by_alias = Institution::by_alias_key();

        if ($by_alias === []) {
            return $rows;
        }

        $named = self::existing_by_key(Institution::class, array_column($rows, 'institution_key'));

        foreach ($rows as $line_number => $row) {
            $key = $row['institution_key'];

            if (! isset($named[$key]) && isset($by_alias[$key])) {
                $rows[$line_number]['institution_key'] = $by_alias[$key]->name_key;
            }
        }

        return $rows;
    }

    /**
     * Lines repeating an institution + course from an earlier line.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    private static function duplicate_pairs(array $rows): array
    {
        $first_line = [];
        $problems = [];

        foreach ($rows as $line_number => $row) {
            $pair = $row['institution_key']."\n".$row['course_key'];

            if (isset($first_line[$pair])) {
                $problems[$line_number] = "Line {$line_number}: {$row['institution']} / {$row['course']} is already on line {$first_line[$pair]}.";

                continue;
            }

            $first_line[$pair] = $line_number;
        }

        return $problems;
    }

    /**
     * Work out type/ownership/state per institution and spot contradictions.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, Institution>  $institutions  Existing, by name key.
     * @return array{0: array<string, array<string, BackedEnum>>, 1: array<int, string>} Details to save by name key, and problems.
     */
    private static function institution_details(array $rows, array $institutions): array
    {
        $details = [];
        $source_line = [];
        $problems = [];

        foreach ($rows as $line_number => $row) {
            $key = $row['institution_key'];
            $existing = $institutions[$key] ?? null;
            $line_problems = [];

            foreach ($row['details'] as $column => $case) {
                $stored = $existing?->getAttribute($column);

                if ($stored !== null && $stored !== $case) {
                    $line_problems[] = "{$existing->name} is recorded with {$column} {$stored->getLabel()}, but this line says {$case->getLabel()}. Correct the line, or edit the institution first";

                    continue;
                }

                if (isset($details[$key][$column]) && $details[$key][$column] !== $case) {
                    $line_problems[] = "{$column} {$case->getLabel()} differs from line {$source_line[$key][$column]} ({$details[$key][$column]->getLabel()}) for {$row['institution']}";

                    continue;
                }

                if ($stored === null && ! isset($details[$key][$column])) {
                    $details[$key][$column] = $case;
                    $source_line[$key][$column] = $line_number;
                }
            }

            if ($line_problems !== []) {
                $problems[$line_number] = "Line {$line_number}: ".implode('; ', $line_problems).'.';
            }
        }

        return [$details, $problems];
    }

    /**
     * Save everything (inside the import transaction).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, Institution>  $institutions
     * @param  array<string, Course>  $courses
     * @param  array<string, array<string, BackedEnum>>  $institution_details
     * @return array{created: int, updated: int, unchanged: int, institutions_created: int, courses_created: int, institutions_missing_details: array<int, string>}
     */
    private static function apply(array $rows, array $institutions, array $courses, array $institution_details): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'institutions_created' => 0, 'courses_created' => 0];

        foreach ($rows as $row) {
            $institution_key = $row['institution_key'];

            if (! isset($institutions[$institution_key])) {
                $institutions[$institution_key] = Institution::query()->create(['name' => $row['institution']] + ($institution_details[$institution_key] ?? []));
                $result['institutions_created']++;
            } elseif (isset($institution_details[$institution_key])) {
                $institutions[$institution_key]->fill($institution_details[$institution_key])->save();
            }

            unset($institution_details[$institution_key]);

            if (! isset($courses[$row['course_key']])) {
                $courses[$row['course_key']] = Course::query()->create(['name' => $row['course']]);
                $result['courses_created']++;
            }

            $requirement = AdmissionRequirement::query()->firstOrNew([
                'institution_id' => $institutions[$institution_key]->id,
                'course_id' => $courses[$row['course_key']]->id,
            ]);
            $was_new = ! $requirement->exists;

            $requirement->fill([
                'cut_off_mark' => $row['cut_off_mark'],
                'olevel_credits_required' => $row['olevel_credits_required'],
            ]);

            $outcome = match (true) {
                $was_new => 'created',
                $requirement->isDirty() => 'updated',
                default => 'unchanged',
            };
            $requirement->save();
            $result[$outcome]++;
        }

        $result['institutions_missing_details'] = collect($institutions)
            ->filter(fn (Institution $institution): bool => $institution->isMissingDetails())
            ->map(fn (Institution $institution): string => $institution->name)
            ->sort()
            ->values()
            ->all();

        return $result;
    }
}
