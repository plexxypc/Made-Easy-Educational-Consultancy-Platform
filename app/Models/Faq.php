<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A frequently asked question (PDF section 13): /faq and the homepage.
 *
 * @property int $id
 * @property string $question
 * @property string $answer Plain text; blank lines separate paragraphs.
 * @property int $sort_order Lower first.
 * @property bool $is_active Hidden from the public site when false.
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['question', 'answer', 'sort_order', 'is_active'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Active questions in display order.
     *
     * @param  Builder<Faq>  $query
     * @return void
     */
    #[Scope]
    protected function shown(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public function answerParagraphs(): array
    {
        return SiteSetting::split_paragraphs($this->answer);
    }

    /**
     * schema.org FAQPage JSON-LD for these questions (Google's FAQ rich
     * result format). Answers are plain text, so no HTML needs stripping.
     *
     * @param  iterable<Faq>  $faqs
     * @return string JSON, safe to place inside a <script> tag.
     */
    public static function structured_data(iterable $faqs): string
    {
        $entities = [];

        foreach ($faqs as $faq) {
            $entities[] = [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => implode("\n\n", $faq->answerParagraphs())],
            ];
        }

        return (string) json_encode(
            ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR,
        );
    }
}
