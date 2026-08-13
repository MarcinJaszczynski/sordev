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
        $contract->loadMissing('event');
        abort_unless($contract->event, 404);
        \Illuminate\Support\Facades\Gate::authorize('view', $contract->event);

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

    public function package(Contract $contract): Response
    {
        $contract->loadMissing('event');
        abort_unless($contract->event, 404);
        \Illuminate\Support\Facades\Gate::authorize('view', $contract->event);

        $package = app(AgreementDocumentService::class)->renderPdfPackageZip($contract);

        abort_if($package === null, 404, 'Brak treści umowy do wygenerowania pakietu.');

        return response($package['bytes'], 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$package['filename'].'"',
        ]);
    }
}
