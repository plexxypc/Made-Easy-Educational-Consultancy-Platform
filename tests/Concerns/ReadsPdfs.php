<?php

namespace Tests\Concerns;

use Smalot\PdfParser\Parser;

/**
 * Reads the text back out of generated PDFs, to check what they say.
 */
trait ReadsPdfs
{
    /**
     * The PDF's text with whitespace collapsed.
     *
     * @param  string  $pdf_bytes
     * @return string
     */
    protected function pdf_text(string $pdf_bytes): string
    {
        $this->assertStringStartsWith('%PDF-', $pdf_bytes, 'Not a PDF.');

        $text = (new Parser)->parseContent($pdf_bytes)->getText();

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
