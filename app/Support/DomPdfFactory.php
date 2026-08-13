<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * DomPDF z ustawieniami pod polskie znaki (DejaVu Sans).
 *
 * Uwaga: font-weight 500/600 w CSS powoduje fallback bez glifów PL —
 * w szablonach PDF używaj bold / 700.
 */
final class DomPdfFactory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function loadView(string $view, array $data = []): DomPdf
    {
        return self::configure(Pdf::loadView($view, $data));
    }

    public static function loadHTML(string $html): DomPdf
    {
        return self::configure(Pdf::loadHTML($html));
    }

    private static function configure(DomPdf $pdf): DomPdf
    {
        $pdf->setPaper('a4');
        $pdf->setOption('defaultFont', 'DejaVu Sans');
        $pdf->setOption('isHtml5ParserEnabled', true);
        $pdf->setOption('isFontSubsettingEnabled', true);

        return $pdf;
    }
}
