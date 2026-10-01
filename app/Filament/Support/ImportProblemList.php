<?php

namespace App\Filament\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Body of a "Nothing was imported" notification: the first problems, one
 * per line, and how many more there are. Shared by the PIN and admission
 * requirement uploads.
 */
class ImportProblemList
{
    /**
     * @param  array<int, string>  $problems
     * @param  int  $limit
     * @return HtmlString
     */
    public static function html(array $problems, int $limit = 10): HtmlString
    {
        $lines = array_map('e', array_slice($problems, 0, $limit));
        $more = count($problems) - count($lines);

        if ($more > 0) {
            $lines[] = e('…and '.Str::plural('more line', $more, true).'.');
        }

        return new HtmlString(implode('<br>', $lines));
    }
}
