<?php

namespace App\Http\Controllers\Front;

use App\Actions\Finance\GenerateInstallmentPaymentLinkAction;
use App\Data\GenerateInstallmentPaymentLinkData;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreement;
use App\Services\AgreementFlowSessionStore;
use App\Services\AgreementParticipantConsentSyncService;
use App\Services\ClientPortalProvisioningService;
use App\Services\ContractInstallmentCheckoutService;
use App\Services\PublicAgreementResolver;
use App\Support\SecurityEnvironment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class AgreementFlowController extends Controller
{
    public function __construct(
        protected PublicAgreementResolver $agreements,
        protected AgreementParticipantConsentSyncService $participantConsentSync,
        protected ContractInstallmentCheckoutService $installmentCheckout,
        protected AgreementFlowSessionStore $flowSession,
    ) {}

    public function show(string $token)
    {
        $agreement = $this->findAgreement($token);

        // Szablon: render bez klonu — umowa powstaje dopiero po danych osobowych.
        if ($this->isTemplate($agreement)) {
            return view('front.agreements.show', [
                'agreement' => $agreement,
                'flow' => $this->effectiveFlow($agreement),
            ]);
        }

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if (in_array($agreement->status, ['signed', 'completed'], true)
            && in_array($agreement->payment_status, ['pending', 'partial'], true)
        ) {
            return redirect()->route('agreement.flow.payment', ['token' => $agreement->public_token]);
        }

        return view('front.agreements.show', [
            'agreement' => $agreement,
            'flow' => $this->effectiveFlow($agreement),
        ]);
    }

    public function confirmPlan(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        $payload = $request->validate([
            'travel_insurance' => ['required', Rule::in(['yes', 'no'])],
        ], [
            'travel_insurance.required' => 'Wybierz opcję dodatkowego ubezpieczenia, aby kontynuować.',
        ]);

        $flowPatch = [
            'plan_confirmed_at' => now()->toIso8601String(),
            'travel_insurance' => $payload['travel_insurance'],
        ];

        if ($this->isTemplate($agreement)) {
            $this->flowSession->merge($agreement, $flowPatch);

            return redirect()->route('agreement.flow.consents', ['token' => $agreement->public_token]);
        }

        $this->mergeMeta($agreement, ['flow' => $flowPatch]);
        $agreement->refresh();
        $agreement->regenerateAgreementBody();

        return redirect()->route('agreement.flow.consents', ['token' => $agreement->public_token]);
    }

    public function consents(string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if ($agreement->status === 'signed') {
            return redirect()->route('agreement.flow.payment', ['token' => $agreement->public_token]);
        }

        if (! $this->isPlanConfirmed($agreement)) {
            return redirect()
                ->route('agreement.flow.show', ['token' => $agreement->public_token])
                ->with('info', 'Najpierw potwierdź plan wycieczki.');
        }

        return view('front.agreements.consents', [
            'agreement' => $agreement,
            'flow' => $this->effectiveFlow($agreement),
        ]);
    }

    public function storeConsents(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        $request->validate([
            'consent_terms' => ['accepted'],
            'consent_insurance' => ['accepted'],
            'consent_data' => ['accepted'],
            'consent_comm' => ['accepted'],
        ], [
            'consent_terms.accepted' => 'Zaakceptuj warunki uczestnictwa.',
            'consent_insurance.accepted' => 'Zaakceptuj warunki ubezpieczenia.',
            'consent_data.accepted' => 'Zaakceptuj przetwarzanie danych osobowych.',
            'consent_comm.accepted' => 'Zaakceptuj zgodę na komunikację elektroniczną.',
        ]);

        $consents = [
            'terms' => true,
            'insurance' => true,
            'data' => true,
            'communication' => true,
            'accepted' => true,
            'accepted_at' => now()->toIso8601String(),
        ];

        if ($this->isTemplate($agreement)) {
            $this->flowSession->merge($agreement, ['consents' => $consents]);

            return redirect()->route('agreement.flow.personal', ['token' => $agreement->public_token]);
        }

        $this->mergeMeta($agreement, [
            'flow' => [
                'consents' => $consents,
            ],
        ]);

        $this->participantConsentSync->syncFromAgreement($agreement->fresh(), $request->ip());

        return redirect()->route('agreement.flow.personal', ['token' => $agreement->public_token]);
    }

    public function personalData(string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if ($agreement->status === 'signed') {
            return redirect()->route('agreement.flow.payment', ['token' => $agreement->public_token]);
        }

        if (! $this->hasConsents($agreement)) {
            return redirect()
                ->route('agreement.flow.consents', ['token' => $agreement->public_token])
                ->with('info', 'Najpierw zaakceptuj wymagane zgody.');
        }

        return view('front.agreements.personal', [
            'agreement' => $agreement,
            'flow' => $this->effectiveFlow($agreement),
        ]);
    }

    public function storePersonalData(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if (! $this->hasConsents($agreement)) {
            return redirect()->route('agreement.flow.consents', ['token' => $agreement->public_token]);
        }

        $payload = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
            'signer_phone' => [Rule::requiredIf($agreement->isIndividual()), 'string', 'max:64'],
            'signer_address_street' => [Rule::requiredIf($agreement->isIndividual()), 'string', 'max:255'],
            'signer_address_number' => [Rule::requiredIf($agreement->isIndividual()), 'string', 'max:64'],
            'signer_postal_code' => [Rule::requiredIf($agreement->isIndividual()), 'string', 'max:32'],
            'signer_city' => [Rule::requiredIf($agreement->isIndividual()), 'string', 'max:120'],
            'signer_province' => ['nullable', 'string', 'max:120'],
            'participant_name' => [Rule::requiredIf($agreement->isIndividual()), 'string', 'max:255'],
            'participant_birth_date' => [Rule::requiredIf($agreement->isIndividual()), 'date', 'before:today'],
            'participant_email' => ['nullable', 'email', 'max:255'],
            'participant_phone' => ['nullable', 'string', 'max:64'],
        ]);

        $sessionFlow = [];
        if ($this->isTemplate($agreement)) {
            $sessionFlow = $this->flowSession->get($agreement);
            $template = $agreement;
            $agreement = $this->agreements->createFromTemplate($template);
            $this->flowSession->clear($template);

            // Przenieś plan/zgody z sesji na nową umowę.
            $this->mergeMeta($agreement, [
                'flow' => array_merge($sessionFlow, [
                    'materialized_from_template_at' => now()->toIso8601String(),
                ]),
            ]);
            $agreement->refresh();
        }

        $participantName = $payload['participant_name']
            ?? $agreement->participant_name
            ?? $agreement->participantPayment?->participant_name;

        $agreement->fill([
            'signer_name' => $payload['signer_name'],
            'signer_email' => $payload['signer_email'],
            'signer_phone' => $payload['signer_phone'] ?? null,
            'participant_name' => $participantName,
            'participant_birth_date' => $payload['participant_birth_date'] ?? null,
            'participant_email' => $payload['participant_email'] ?? null,
            'participant_phone' => $payload['participant_phone'] ?? null,
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        if ($agreement->isIndividual()) {
            // Zachowaj slots ze szablonu (np. rodzeństwo); nie resetuj do 1.
            $slots = max(
                1,
                (int) data_get($agreement->meta, 'participants_on_contract', $agreement->participant_count ?? 1)
            );
            $agreement->participant_count = $slots;

            // Zamawiający = płatnik (osoba wypełniająca formularz).
            $agreement->customer_name = $payload['signer_name'];
            $agreement->customer_email = $payload['signer_email'];
            $agreement->customer_phone = $payload['signer_phone'] ?? null;

            if ((float) $agreement->amount_due <= 0) {
                $agreement->amount_due = $agreement->resolveIndividualAmountDue();
            }
        }

        $agreement->save();

        $this->mergeMeta($agreement, [
            'awaiting_participant_details' => false,
            'flow' => [
                'personal_completed_at' => now()->toIso8601String(),
                'signer_address' => [
                    'street' => $payload['signer_address_street'] ?? null,
                    'number' => $payload['signer_address_number'] ?? null,
                    'postal_code' => $payload['signer_postal_code'] ?? null,
                    'city' => $payload['signer_city'] ?? null,
                    'province' => $payload['signer_province'] ?? null,
                ],
            ],
        ]);

        $agreement->refresh();
        $agreement->regenerateAgreementBody();

        $this->participantConsentSync->syncFromAgreement($agreement->fresh(), $request->ip());
        $this->provisionPortalOnce($agreement->fresh());

        if ((bool) data_get($agreement->meta, 'skip_payment', false)) {
            $agreement->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'amount_paid' => 0,
                'paid_at' => now(),
            ]);
            $this->mergeMeta($agreement, [
                'flow' => [
                    'payment_skipped_data_collection' => true,
                    'payment_completed_at' => now()->toIso8601String(),
                ],
            ]);
            $this->sendConfirmationEmail($agreement->fresh());

            return redirect()
                ->route('agreement.flow.success', ['token' => $agreement->public_token])
                ->with('success', 'Dane zapisane. Masz dostęp do panelu imprezy.');
        }

        return redirect()
            ->route('agreement.flow.payment', ['token' => $agreement->public_token])
            ->with('success', 'Dane zapisane. Przejdź do płatności pierwszej raty.');
    }

    public function sign(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($this->isTemplate($agreement)) {
            return redirect()
                ->route('agreement.flow.show', ['token' => $agreement->public_token])
                ->with('info', 'Wypełnij formularz krok po kroku.');
        }

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        $payload = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
            'signer_phone' => ['nullable', 'string', 'max:64'],
            'accept_terms' => ['accepted'],
            'accept_payment' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Musisz zaakceptować warunki umowy.',
            'accept_payment.accepted' => 'Musisz zaakceptować warunki płatności.',
        ]);

        $agreement->update([
            'signer_name' => $payload['signer_name'],
            'signer_email' => $payload['signer_email'],
            'signer_phone' => $payload['signer_phone'] ?? null,
            'participant_name' => $agreement->participant_name ?: ($agreement->isIndividual() ? $payload['signer_name'] : null),
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $this->mergeMeta($agreement, [
            'flow' => [
                'plan_confirmed_at' => now()->toIso8601String(),
                'consents' => [
                    'terms' => true,
                    'insurance' => true,
                    'data' => true,
                    'communication' => true,
                    'accepted' => true,
                    'accepted_at' => now()->toIso8601String(),
                ],
                'personal_completed_at' => now()->toIso8601String(),
            ],
        ]);

        $agreement->refresh();
        $agreement->regenerateAgreementBody();

        $this->participantConsentSync->syncFromAgreement($agreement->fresh(), $request->ip());
        $this->provisionPortalOnce($agreement->fresh());

        return redirect()
            ->route('agreement.flow.payment', ['token' => $agreement->public_token])
            ->with('success', 'Umowa została zawarta. Przejdź do płatności.');
    }

    public function payment(string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($this->isTemplate($agreement)) {
            return redirect()->route('agreement.flow.show', ['token' => $agreement->public_token]);
        }

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        $canPay = in_array($agreement->status, ['signed', 'completed'], true);
        if (! $canPay) {
            if ($this->hasConsents($agreement)) {
                return redirect()->route('agreement.flow.personal', ['token' => $agreement->public_token]);
            }

            if ($this->isPlanConfirmed($agreement)) {
                return redirect()->route('agreement.flow.consents', ['token' => $agreement->public_token]);
            }

            return redirect()->route('agreement.flow.show', ['token' => $agreement->public_token]);
        }

        return view('front.agreements.payment', [
            'agreement' => $agreement->loadMissing(['paymentSchedules', 'participantPayment', 'event']),
            'methods' => Contract::$paymentMethods,
            'flow' => $this->effectiveFlow($agreement),
            'checkout' => $agreement instanceof Contract
                ? $this->installmentCheckout->snapshot($agreement)
                : null,
        ]);
    }

    public function pay(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($this->isTemplate($agreement)) {
            return redirect()->route('agreement.flow.show', ['token' => $agreement->public_token]);
        }

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if ($agreement->status !== 'signed' && $agreement->status !== 'completed') {
            return redirect()->route('agreement.flow.personal', ['token' => $agreement->public_token]);
        }

        // Demo „opłać bieżącą ratę” tylko poza produkcją + driver fake.
        if (SecurityEnvironment::allowsFakePayments()) {
            $rules = [
                'payment_method' => ['required', Rule::in(array_keys(Contract::$paymentMethods))],
                'accept_demo' => ['accepted'],
                'fx_location' => ['nullable', 'array'],
                'fx_location.*' => ['in:office,pilot'],
            ];

            $payload = $request->validate($rules, [
                'accept_demo.accepted' => 'Potwierdź realizację płatności demo.',
            ]);

            if ($agreement instanceof Contract) {
                try {
                    $fxLocations = is_array($payload['fx_location'] ?? null) ? $payload['fx_location'] : [];
                    $checkoutBefore = $this->installmentCheckout->snapshot($agreement);
                    $nextRemaining = (float) ($checkoutBefore['next_pln']['remaining'] ?? 0);

                    // Tylko zapis miejsca FX (PLN już rozliczony).
                    if ($nextRemaining <= 0.009) {
                        $this->installmentCheckout->persistFxLocations($agreement, $fxLocations);
                        $this->mergeMeta($agreement->fresh(), [
                            'flow' => [
                                'last_payment' => [
                                    'contract_id' => $agreement->id,
                                    'schedule_id' => null,
                                    'amount' => 0,
                                    'method' => $payload['payment_method'],
                                    'at' => now()->toIso8601String(),
                                    'next' => 'done',
                                    'total_remaining_pln' => 0,
                                ],
                                'fx_location_saved_at' => now()->toIso8601String(),
                                'payment_method' => $payload['payment_method'],
                            ],
                        ]);

                        $this->sendConfirmationEmail($agreement->fresh());

                        return redirect()
                            ->route('agreement.flow.success', ['token' => $agreement->public_token])
                            ->with('success', 'Zapisano miejsce płatności walutowej.');
                    }

                    $result = $this->installmentCheckout->applyDemoPlnPayment(
                        $agreement,
                        (string) $payload['payment_method'],
                        $fxLocations,
                    );
                } catch (InvalidArgumentException $e) {
                    return redirect()
                        ->route('agreement.flow.payment', ['token' => $agreement->public_token])
                        ->withErrors(['payment' => $e->getMessage()]);
                }

                $this->mergeMeta($agreement->fresh(), [
                    'flow' => [
                        'last_payment' => [
                            'contract_id' => $agreement->id,
                            'schedule_id' => $result['schedule_id'],
                            'amount' => $result['charged'],
                            'method' => $payload['payment_method'],
                            'at' => now()->toIso8601String(),
                            'next' => $result['snapshot']['next_step'],
                            'total_remaining_pln' => $result['snapshot']['total_remaining_pln'],
                        ],
                        'payment_method' => $payload['payment_method'],
                    ],
                ]);

                $this->agreements->syncPayment($agreement->fresh());

                $fresh = $agreement->fresh();
                $this->provisionPortalOnce($fresh);
                // Po 1. racie w /umowa kończymy flow — kolejne raty w portalu / mailu.
                $this->sendConfirmationEmail($fresh);

                $msg = 'Opłacono ratę '.number_format($result['charged'], 2, ',', ' ').' PLN.';
                if (($result['snapshot']['total_remaining_pln'] ?? 0) > 0.009) {
                    $msg .= ' Kolejne transze opłacisz w portalu klienta (linki także w mailu potwierdzającym).';
                } else {
                    $msg .= ' Umowa jest rozliczona po stronie PLN.';
                }

                return redirect()
                    ->route('agreement.flow.success', ['token' => $fresh->public_token])
                    ->with('success', $msg);
            }

            // Legacy EventAgreement — pełna kwota (brak nowoczesnych rat kontraktowych).
            $agreement->update([
                'status' => 'completed',
                'payment_status' => 'paid',
                'payment_method' => $payload['payment_method'],
                'amount_paid' => (float) $agreement->amount_due,
                'paid_at' => now(),
            ]);

            $this->mergeMeta($agreement, [
                'flow' => [
                    'payment_completed_at' => now()->toIso8601String(),
                    'payment_method' => $payload['payment_method'],
                ],
            ]);

            $this->agreements->syncPayment($agreement);
            $this->provisionPortalOnce($agreement->fresh());
            $this->sendConfirmationEmail($agreement->fresh());

            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        // Produkcja / prawdziwy driver: wybór metody offline → oczekuje na księgowość (nie „paid”).
        $payload = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(Contract::$paymentMethods))],
            'fx_location' => ['nullable', 'array'],
            'fx_location.*' => ['in:office,pilot'],
        ]);

        if ($agreement instanceof Contract) {
            $this->installmentCheckout->persistFxLocations(
                $agreement,
                is_array($payload['fx_location'] ?? null) ? $payload['fx_location'] : [],
            );
            $checkout = $this->installmentCheckout->snapshot($agreement->fresh());
            $awaitingAmount = (float) ($checkout['next_pln']['remaining'] ?? $checkout['total_remaining_pln']);
        } else {
            $awaitingAmount = (float) $agreement->amount_due;
            $checkout = null;
        }

        $agreement->update([
            'status' => 'completed',
            'payment_status' => ((float) ($agreement->amount_paid ?? 0) > 0.009) ? 'partial' : 'pending',
            'payment_method' => $payload['payment_method'],
        ]);

        $this->mergeMeta($agreement, [
            'flow' => [
                'payment_method_selected_at' => now()->toIso8601String(),
                'payment_method' => $payload['payment_method'],
                'awaiting_offline_payment' => true,
                'awaiting_amount_pln' => $awaitingAmount,
                'last_payment' => [
                    'contract_id' => $agreement->id,
                    'schedule_id' => $checkout['next_pln']['id'] ?? null,
                    'amount' => $awaitingAmount,
                    'method' => $payload['payment_method'],
                    'at' => now()->toIso8601String(),
                    'next' => $checkout['next_step'] ?? 'pay_pln',
                ],
            ],
        ]);

        $this->provisionPortalOnce($agreement->fresh());
        $this->sendConfirmationEmail($agreement->fresh());

        return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
    }

    public function success(string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($this->isTemplate($agreement)) {
            return redirect()->route('agreement.flow.show', ['token' => $agreement->public_token]);
        }

        $awaitingOffline = (bool) data_get($agreement->meta, 'flow.awaiting_offline_payment', false)
            && in_array($agreement->payment_status, ['pending', 'partial'], true)
            && filled($agreement->payment_method);

        $checkout = $agreement instanceof Contract
            ? $this->installmentCheckout->snapshot($agreement)
            : null;

        $plnSettled = in_array($agreement->payment_status, ['paid', 'partial'], true)
            || ((float) ($agreement->amount_paid ?? 0) > 0.009);

        if ($agreement->payment_status !== 'paid' && ! $awaitingOffline && ! $plnSettled) {
            return redirect()->route('agreement.flow.payment', ['token' => $agreement->public_token]);
        }

        // Finalizuj umowę klonowaną z szablonu (zmień na sent zamiast completed)
        if (($agreement->meta['parent_template_id'] ?? null) && $agreement->status === 'completed') {
            $agreement->update(['status' => 'sent']);
        }

        return view('front.agreements.success', [
            'agreement' => $agreement,
            'flow' => $this->effectiveFlow($agreement),
            'checkout' => $checkout,
            'portalLoginUrl' => url('/portal/login'),
        ]);
    }

    protected function findAgreement(string $token): Contract|EventAgreement
    {
        return $this->agreements->findByToken($token);
    }

    protected function isTemplate(Contract|EventAgreement $agreement): bool
    {
        return $agreement->status === 'template';
    }

    /**
     * @return array<string, mixed>
     */
    protected function effectiveFlow(Contract|EventAgreement $agreement): array
    {
        if ($this->isTemplate($agreement)) {
            return $this->flowSession->get($agreement);
        }

        return (array) data_get($agreement->meta, 'flow', []);
    }

    protected function mergeMeta(Contract|EventAgreement $agreement, array $patch): void
    {
        $current = (array) ($agreement->meta ?? []);
        $agreement->meta = array_replace_recursive($current, $patch);
        $agreement->saveQuietly();
    }

    protected function isPlanConfirmed(Contract|EventAgreement $agreement): bool
    {
        if ($this->isTemplate($agreement)) {
            return $this->flowSession->isPlanConfirmed($agreement);
        }

        return filled(data_get($agreement->meta, 'flow.plan_confirmed_at'));
    }

    protected function hasConsents(Contract|EventAgreement $agreement): bool
    {
        if ($this->isTemplate($agreement)) {
            return $this->flowSession->hasConsents($agreement);
        }

        return (bool) data_get($agreement->meta, 'flow.consents.accepted', false);
    }

    protected function provisionPortalOnce(Contract|EventAgreement $agreement): void
    {
        if (filled(data_get($agreement->meta, 'flow.portal_provisioned_at'))) {
            return;
        }

        app(ClientPortalProvisioningService::class)->provisionFromAgreement($agreement);

        $this->mergeMeta($agreement->fresh(), [
            'flow' => [
                'portal_provisioned_at' => now()->toIso8601String(),
            ],
        ]);
    }

    protected function sendConfirmationEmail(Contract|EventAgreement $agreement): void
    {
        $recipient = $agreement->signer_email ?: $agreement->customer_email;

        if (blank($recipient)) {
            return;
        }

        $lines = [
            'Dziękujemy za zawarcie umowy.',
            '',
            'Numer umowy: '.($agreement->agreement_number ?: ('#'.$agreement->id)),
            'Typ umowy: '.$agreement->agreement_type_label,
            'Impreza: '.($agreement->event_name ?: ($agreement->event?->name ?? '—')),
            'Uczestnik: '.($agreement->participant_name ?: '—'),
            'Kwota opłacona: '.number_format((float) $agreement->amount_paid, 2, ',', ' ').' '.strtoupper((string) ($agreement->currency ?: 'PLN')),
            'Data płatności: '.optional($agreement->paid_at)->format('d.m.Y H:i'),
            '',
            'Link do umowy: '.$agreement->public_link,
            'Portal klienta: '.url('/portal/login'),
        ];

        $paymentLinks = $this->remainingInstallmentPaymentLines($agreement);
        if ($paymentLinks !== []) {
            $lines[] = '';
            $lines[] = 'Kolejne płatności (linki jednorazowe):';
            foreach ($paymentLinks as $line) {
                $lines[] = $line;
            }
        }

        $subject = sprintf('Potwierdzenie zawarcia umowy %s', $agreement->agreement_number ?: ('#'.$agreement->id));
        $body = implode("\n", $lines);

        try {
            Mail::raw($body, function ($message) use ($agreement, $recipient, $subject): void {
                $message->to($recipient)->subject($subject);

                if (! empty($agreement->customer_email) && $agreement->customer_email !== $recipient) {
                    $message->cc($agreement->customer_email);
                }

                $pdfBytes = $this->renderAgreementPdf($agreement);

                if (! empty($pdfBytes)) {
                    $fileName = 'umowa-'.($agreement->agreement_number ?: $agreement->id).'.pdf';
                    $safeName = Str::of($fileName)
                        ->replace(['/', '\\', ' '], ['-', '-', '_'])
                        ->value();

                    $message->attachData($pdfBytes, $safeName, [
                        'mime' => 'application/pdf',
                    ]);
                }

                foreach ((array) ($agreement->attachments ?? []) as $relativePath) {
                    if (! $relativePath) {
                        continue;
                    }

                    $absolutePath = $this->resolveAgreementAttachmentAbsolutePath((string) $relativePath);

                    if (! $absolutePath) {
                        Log::warning('agreement-confirmation-attachment-missing', [
                            'agreement_id' => $agreement->id,
                            'path' => $relativePath,
                        ]);

                        continue;
                    }

                    $message->attach($absolutePath, [
                        'as' => basename($absolutePath),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::error('agreement-confirmation-mail-failed', [
                'agreement_id' => $agreement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    protected function remainingInstallmentPaymentLines(Contract|EventAgreement $agreement): array
    {
        if (! $agreement instanceof Contract || ! Schema::hasTable('contract_payment_schedules')) {
            return [];
        }

        $action = app(GenerateInstallmentPaymentLinkAction::class);
        $lines = [];

        $schedules = $agreement->paymentSchedules()
            ->orderBy('sort_order')
            ->get()
            ->filter(function (ContractPaymentSchedule $row): bool {
                $amount = round((float) $row->amount, 2);
                if ($amount <= 0.009) {
                    return false;
                }

                $paid = round((float) ($row->paid_amount ?? 0), 2);

                return ($amount - $paid) > 0.009;
            });

        foreach ($schedules as $schedule) {
            $remaining = round((float) $schedule->amount - (float) ($schedule->paid_amount ?? 0), 2);
            $label = $schedule->label ?: 'Transza';
            $window = $this->formatScheduleDueWindow($schedule);

            try {
                $link = $action(new GenerateInstallmentPaymentLinkData(schedule: $schedule, ttlDays: 30));
                $lines[] = sprintf(
                    '- %s: %s PLN%s — %s',
                    $label,
                    number_format($remaining, 2, ',', ' '),
                    $window !== '' ? ' ('.$window.')' : '',
                    $link['url'],
                );
            } catch (\Throwable $e) {
                Log::warning('agreement-confirmation-payment-link-failed', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $lines;
    }

    protected function formatScheduleDueWindow(ContractPaymentSchedule $schedule): string
    {
        $from = null;
        $to = null;

        if (Schema::hasColumn($schedule->getTable(), 'due_from') && $schedule->due_from) {
            $from = $schedule->due_from->format('d.m.Y');
        }
        if (Schema::hasColumn($schedule->getTable(), 'due_to') && $schedule->due_to) {
            $to = $schedule->due_to->format('d.m.Y');
        } elseif ($schedule->due_date) {
            $to = $schedule->due_date->format('d.m.Y');
        }

        if ($from && $to) {
            return 'termin '.$from.'–'.$to;
        }
        if ($to) {
            return 'do '.$to;
        }
        if ($from) {
            return 'od '.$from;
        }

        return '';
    }

    protected function renderAgreementPdf(Contract|EventAgreement $agreement): ?string
    {
        return app(\App\Services\AgreementDocumentService::class)->renderPdfBytes($agreement);
    }

    protected function resolveAgreementAttachmentAbsolutePath(string $path): ?string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            return null;
        }

        if (is_file($trimmedPath)) {
            return $trimmedPath;
        }

        $candidates = [
            ltrim($trimmedPath, '/'),
            Str::replaceFirst('storage/', '', ltrim($trimmedPath, '/')),
            Str::replaceFirst('public/', '', ltrim($trimmedPath, '/')),
            Str::replaceFirst('public/storage/', '', ltrim($trimmedPath, '/')),
        ];

        foreach (array_unique($candidates) as $candidate) {
            if (Storage::disk('public')->exists($candidate)) {
                return Storage::disk('public')->path($candidate);
            }
        }

        return null;
    }
}
