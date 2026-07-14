<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EventAgreement;
use Barryvdh\DomPDF\Facade\Pdf;

class AgreementDocumentService
{
    public function renderPdfBytes(Contract|EventAgreement $agreement): ?string
    {
        $agreement->loadMissing([
            'event',
            'orderingParties.contractor',
            'paymentSchedules',
        ]);

        if (method_exists($agreement, 'resolveAgreementPdfBytes')) {
            $uploaded = $agreement->resolveAgreementPdfBytes();

            if (filled($uploaded)) {
                return $uploaded;
            }
        }

        if (blank($agreement->agreement_body)) {
            return null;
        }

        $html = view('pdf.agreement', [
            'agreement' => $agreement,
            'agreementBody' => (string) $agreement->agreement_body,
            'groupPricing' => app(ContractGroupPricingService::class)->presentationFor($agreement),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->output();
    }
}
