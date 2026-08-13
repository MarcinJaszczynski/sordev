<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Finance\CompleteOnlinePaymentAction;
use App\Actions\Finance\InitiateOnlinePaymentAction;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreementPaymentSchedule;
use App\Models\OnlinePaymentSession;
use App\Support\MoneyFormatter;
use App\Support\SecurityEnvironment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Checkout i finalizacja płatności online.
 *
 * Kontrakt callback (produkcyjny gateway):
 * 1) Gateway redirect/webhook → weryfikacja podpisu (PaymentGateway::verifyCallback)
 * 2) Znalezienie OnlinePaymentSession po uuid / external_id
 * 3) CompleteOnlinePaymentAction (idempotentne przy STATUS_PAID)
 * 4) Redirect na payments.online.success
 *
 * Fake driver: fakeCheckout + fakePay (dev/test). Produkcyjny provider (PayU/TPay) —
 * osobna integracja, ten sam CompleteOnlinePaymentAction.
 */
class OnlinePaymentController extends Controller
{
    public function startFromInstallment(Request $request, string $type, int $schedule): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $model = match ($type) {
            'contract' => ContractPaymentSchedule::query()->with(['contract.event'])->findOrFail($schedule),
            'agreement' => EventAgreementPaymentSchedule::query()->with(['eventAgreement.event'])->findOrFail($schedule),
            default => abort(404),
        };

        $email = $type === 'contract'
            ? ($model->contract?->client_email ?? null)
            : ($model->eventAgreement?->client_email ?? null);

        $result = app(InitiateOnlinePaymentAction::class)($model, $email);

        return redirect()->away($result['checkout_url']);
    }

    public function fakeCheckout(string $uuid): View
    {
        abort_unless(SecurityEnvironment::allowsFakePayments(), 404);

        $session = OnlinePaymentSession::query()->where('uuid', $uuid)->firstOrFail();

        return view('payments.online-fake-checkout', [
            'session' => $session,
            'amountLabel' => MoneyFormatter::format((float) $session->amount, $session->currency),
        ]);
    }

    public function fakePay(string $uuid): RedirectResponse
    {
        abort_unless(SecurityEnvironment::allowsFakePayments(), 404);

        $session = OnlinePaymentSession::query()->where('uuid', $uuid)->firstOrFail();
        app(CompleteOnlinePaymentAction::class)($session);

        return redirect()->route('payments.online.success', ['uuid' => $session->uuid]);
    }

    public function success(string $uuid): View
    {
        $session = OnlinePaymentSession::query()->where('uuid', $uuid)->firstOrFail();

        return view('payments.online-success', [
            'session' => $session,
            'amountLabel' => MoneyFormatter::format((float) $session->amount, $session->currency),
        ]);
    }
}
