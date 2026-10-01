<?php

namespace App\Actions\Faqs;

use App\Models\Faq;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Replace the drafted stand-in FAQs with the official PDF section 13 wording.
 *
 * Rules:
 *  - Exactly OFFICIAL_COUNT questions, none blank (the PDF has seven).
 *  - A drafted row is removed only if its question AND answer are still
 *    exactly as seeded. A draft an admin edited is kept and reported, so
 *    nobody's work disappears silently.
 *  - Official questions are matched by text: re-running updates their order
 *    and re-activates them, but never overwrites an answer an admin edited.
 *  - Questions an admin added themselves are not touched.
 */
class InstallOfficialFaqs
{
    /**
     * How many questions PDF section 13 has.
     *
     * @var int
     */
    public const OFFICIAL_COUNT = 7;

    /**
     * @param  array<int, array{0: string, 1: string}>  $official  [question, answer] pairs in display order.
     * @param  array<int, array{0: string, 1: string}>  $drafted  The stand-ins to remove if untouched.
     * @return array{added: int, removed: int, kept_drafts: array<int, string>}
     *
     * @throws InvalidArgumentException If the official list is incomplete.
     */
    public function handle(array $official, array $drafted): array
    {
        $official = $this->validated($official);

        return DB::transaction(function () use ($official, $drafted): array {
            $removed = 0;
            $kept_drafts = [];
            $official_questions = array_column($official, 0);

            foreach ($drafted as [$question, $answer]) {
                if (in_array($question, $official_questions, true)) {
                    continue;
                }

                $removed += Faq::query()->where('question', $question)->where('answer', $answer)->delete();
            }

            $added = 0;

            foreach ($official as $position => [$question, $answer]) {
                $faq = Faq::query()->firstOrNew(['question' => $question]);
                $added += $faq->exists ? 0 : 1;

                $faq->forceFill([
                    'answer' => $faq->exists ? $faq->answer : $answer,
                    'sort_order' => $position + 1,
                    'is_active' => true,
                ])->save();
            }

            foreach ($drafted as [$question]) {
                if (! in_array($question, $official_questions, true) && Faq::query()->where('question', $question)->exists()) {
                    $kept_drafts[] = $question;
                }
            }

            return ['added' => $added, 'removed' => $removed, 'kept_drafts' => $kept_drafts];
        });
    }

    /**
     * Trimmed, re-indexed pairs; refuses anything but a complete set.
     *
     * @param  array<int, mixed>  $official
     * @return array<int, array{0: string, 1: string}>
     *
     * @throws InvalidArgumentException
     */
    private function validated(array $official): array
    {
        if (count($official) !== self::OFFICIAL_COUNT) {
            throw new InvalidArgumentException(sprintf(
                'Expected %d official FAQs (PDF section 13), got %d.',
                self::OFFICIAL_COUNT,
                count($official),
            ));
        }

        $pairs = [];

        foreach (array_values($official) as $index => $pair) {
            $question = is_array($pair) ? trim((string) ($pair[0] ?? '')) : '';
            $answer = is_array($pair) ? trim((string) ($pair[1] ?? '')) : '';

            if ($question === '' || $answer === '') {
                throw new InvalidArgumentException(sprintf('Official FAQ %d needs both a question and an answer.', $index + 1));
            }

            $pairs[] = [$question, $answer];
        }

        if (count(array_unique(array_column($pairs, 0))) !== count($pairs)) {
            throw new InvalidArgumentException('The official FAQs contain the same question twice.');
        }

        return $pairs;
    }
}
