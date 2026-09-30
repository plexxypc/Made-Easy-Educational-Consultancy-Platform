<?php

namespace App\Actions\Receipts;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The receipt as a PDF (dompdf), used for the receipt link on the order
 * page and dashboard and attached to the receipt email.
 *
 * Built only from the Receipt snapshot, so it always shows what was paid
 * even if the order or service changes later. dompdf runs locked down:
 * no remote files, no PHP, no PDF JavaScript. DejaVu Sans (bundled with
 * dompdf) is used because it has the naira sign (₦); subsetting keeps the
 * file small.
 */
class RenderReceiptPdf
{
    /**
     * @param  Receipt  $receipt
     * @return string PDF bytes.
     */
    public function handle(Receipt $receipt): string
    {
        return Pdf::loadView('receipts.pdf', [
            'receipt' => $receipt,
            'contact' => config('madeeasy.contact'),
        ])
            ->setPaper('a4')
            ->setOption([
                'enable_remote' => false,
                'enable_php' => false,
                'enable_javascript' => false,
                'enable_font_subsetting' => true,
                'default_font' => 'DejaVu Sans',
            ])
            ->output();
    }

    /**
     * @param  Receipt  $receipt
     * @return string e.g. "receipt-MER-000042.pdf"
     */
    public function file_name(Receipt $receipt): string
    {
        return 'receipt-'.$receipt->number.'.pdf';
    }
}
