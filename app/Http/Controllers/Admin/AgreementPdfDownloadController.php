<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Services\AgreementDocumentService;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AgreementPdfDownloadController extends Controller
{
    public function __invoke(Contract $contract): Response
    {
        $pdfBytes = app(AgreementDocumentService::class)->renderPdfBytes($contract);

        abort_if(blank($pdfBytes), 404, 'Brak treści umowy do wygenerowania PDF.');

        $fileName = 'umowa-'.Str::of($contract->contract_number ?: $contract->id)
            ->replace(['/', '\\', ' '], ['-', '-', '_'])
            ->value();

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$fileName.'.pdf"',
        ]);
    }
}
