<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\EventAgreement;
use App\Services\AgreementPaymentSyncService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgreementFlowController extends Controller
{
    public function show(string $token)
    {
        $agreement = $this->findAgreement($token);

        // Jeśli to szablon - utwórz klon dla tego uczestnika
        if ($agreement->status === 'template') {
            return $this->cloneTemplateForParticipant($agreement);
        }

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if ($agreement->status === 'signed') {
            return redirect()->route('agreement.flow.payment', ['token' => $agreement->public_token]);
        }

        return view('front.agreements.show', [
            'agreement' => $agreement,
            'flow' => $this->flowMeta($agreement),
        ]);
    }

    protected function cloneTemplateForParticipant(EventAgreement $template): \Illuminate\Http\RedirectResponse
    {
        // Utwórz draft dla uczestnika na podstawie szablonu
        $agreement = EventAgreement::create([
            'event_id' => $template->event_id,
            'contract_template_id' => $template->contract_template_id,
            'agreement_type' => $template->agreement_type,
            'title' => $template->title,
            'agreement_date' => now()->toDateString(),
            'event_name' => $template->event_name,
            'event_start_date' => $template->event_start_date,
            'event_end_date' => $template->event_end_date,
            'customer_name' => $template->customer_name,
            'customer_email' => $template->customer_email,
            'customer_phone' => $template->customer_phone,
            'participant_count' => $template->participant_count,
            'amount_due' => $template->amount_due,
            'currency' => $template->currency,
            'status' => 'draft',
            'payment_status' => 'pending',
            'attachments' => $template->attachments,
            'admin_notes' => $template->admin_notes,
            'contract_template_id' => $template->contract_template_id,
            'meta' => array_merge($template->meta ?? [], ['parent_template_id' => $template->id]),
        ]);

        $agreement->regenerateAgreementBody();

        return redirect()->route('agreement.flow.show', ['token' => $agreement->public_token]);
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

        $this->mergeMeta($agreement, [
            'flow' => [
                'plan_confirmed_at' => now()->toIso8601String(),
                'travel_insurance' => $payload['travel_insurance'],
            ],
        ]);

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

        if (!$this->isPlanConfirmed($agreement)) {
            return redirect()
                ->route('agreement.flow.show', ['token' => $agreement->public_token])
                ->with('info', 'Najpierw potwierdź plan wycieczki.');
        }

        return view('front.agreements.consents', [
            'agreement' => $agreement,
            'flow' => $this->flowMeta($agreement),
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

        $this->mergeMeta($agreement, [
            'flow' => [
                'consents' => [
                    'terms' => true,
                    'insurance' => true,
                    'data' => true,
                    'communication' => true,
                    'accepted' => true,
                    'accepted_at' => now()->toIso8601String(),
                ],
            ],
        ]);

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

        if (!$this->hasConsents($agreement)) {
            return redirect()
                ->route('agreement.flow.consents', ['token' => $agreement->public_token])
                ->with('info', 'Najpierw zaakceptuj wymagane zgody.');
        }

        return view('front.agreements.personal', [
            'agreement' => $agreement,
            'flow' => $this->flowMeta($agreement),
        ]);
    }

    public function storePersonalData(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if (!$this->hasConsents($agreement)) {
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
            $agreement->participant_count = 1;

            if ((float) $agreement->amount_due <= 0) {
                $agreement->amount_due = $agreement->resolveIndividualAmountDue();
            }
        }

        $agreement->save();

        $this->mergeMeta($agreement, [
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

        return redirect()
            ->route('agreement.flow.payment', ['token' => $agreement->public_token])
            ->with('success', 'Dane zapisane. Przejdź do płatności.');
    }

    public function sign(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

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

        return redirect()
            ->route('agreement.flow.payment', ['token' => $agreement->public_token])
            ->with('success', 'Umowa została zawarta. Przejdź do płatności.');
    }

    public function payment(string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if ($agreement->status !== 'signed') {
            if ($this->hasConsents($agreement)) {
                return redirect()->route('agreement.flow.personal', ['token' => $agreement->public_token]);
            }

            if ($this->isPlanConfirmed($agreement)) {
                return redirect()->route('agreement.flow.consents', ['token' => $agreement->public_token]);
            }

            return redirect()->route('agreement.flow.show', ['token' => $agreement->public_token]);
        }

        return view('front.agreements.payment', [
            'agreement' => $agreement,
            'methods' => EventAgreement::$paymentMethods,
            'flow' => $this->flowMeta($agreement),
        ]);
    }

    public function pay(Request $request, string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status === 'paid') {
            return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
        }

        if ($agreement->status !== 'signed') {
            return redirect()->route('agreement.flow.personal', ['token' => $agreement->public_token]);
        }

        $payload = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(EventAgreement::$paymentMethods))],
            'accept_demo' => ['accepted'],
        ], [
            'accept_demo.accepted' => 'Potwierdź realizację płatności demo.',
        ]);

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

        app(AgreementPaymentSyncService::class)->sync($agreement->fresh());
        $this->sendConfirmationEmail($agreement->fresh());

        return redirect()->route('agreement.flow.success', ['token' => $agreement->public_token]);
    }

    public function success(string $token)
    {
        $agreement = $this->findAgreement($token);

        if ($agreement->payment_status !== 'paid') {
            return redirect()->route('agreement.flow.payment', ['token' => $agreement->public_token]);
        }

        // Finalizuj umowę klonowaną z szablonu (zmień na sent zamiast completed)
        if (($agreement->meta['parent_template_id'] ?? null) && $agreement->status === 'completed') {
            $agreement->update(['status' => 'sent']);
        }

        return view('front.agreements.success', [
            'agreement' => $agreement,
            'flow' => $this->flowMeta($agreement),
        ]);
    }

    protected function findAgreement(string $token): EventAgreement
    {
        $agreement = EventAgreement::query()
            ->with(['event', 'contractTemplate', 'participantPayment'])
            ->where('public_token', $token)
            ->firstOrFail();

        if ($agreement->public_token_expires_at && $agreement->public_token_expires_at->isPast()) {
            abort(410, 'Link do umowy wygasł.');
        }

        return $agreement;
    }

    protected function flowMeta(EventAgreement $agreement): array
    {
        return (array) data_get($agreement->meta, 'flow', []);
    }

    protected function mergeMeta(EventAgreement $agreement, array $patch): void
    {
        $current = (array) ($agreement->meta ?? []);
        $agreement->meta = array_replace_recursive($current, $patch);
        $agreement->saveQuietly();
    }

    protected function isPlanConfirmed(EventAgreement $agreement): bool
    {
        return filled(data_get($agreement->meta, 'flow.plan_confirmed_at'));
    }

    protected function hasConsents(EventAgreement $agreement): bool
    {
        return (bool) data_get($agreement->meta, 'flow.consents.accepted', false);
    }

    protected function sendConfirmationEmail(EventAgreement $agreement): void
    {
        $recipient = $agreement->signer_email ?: $agreement->customer_email;

        if (blank($recipient)) {
            return;
        }

        $subject = sprintf('Potwierdzenie zawarcia umowy %s', $agreement->agreement_number ?: ('#' . $agreement->id));
        $body = implode("\n", [
            'Dziękujemy za zawarcie umowy.',
            '',
            'Numer umowy: ' . ($agreement->agreement_number ?: ('#' . $agreement->id)),
            'Typ umowy: ' . $agreement->agreement_type_label,
            'Impreza: ' . ($agreement->event_name ?: ($agreement->event?->name ?? '—')),
            'Uczestnik: ' . ($agreement->participant_name ?: '—'),
            'Kwota opłacona: ' . number_format((float) $agreement->amount_paid, 2, ',', ' ') . ' ' . strtoupper((string) ($agreement->currency ?: 'PLN')),
            'Data płatności: ' . optional($agreement->paid_at)->format('d.m.Y H:i'),
            '',
            'Link do umowy: ' . $agreement->public_link,
        ]);

        try {
            Mail::raw($body, function ($message) use ($agreement, $recipient, $subject): void {
                $message->to($recipient)->subject($subject);

                if (!empty($agreement->customer_email) && $agreement->customer_email !== $recipient) {
                    $message->cc($agreement->customer_email);
                }

                $pdfBytes = $this->renderAgreementPdf($agreement);

                if (!empty($pdfBytes)) {
                    $fileName = 'umowa-' . ($agreement->agreement_number ?: $agreement->id) . '.pdf';
                    $safeName = Str::of($fileName)
                        ->replace(['/', '\\', ' '], ['-', '-', '_'])
                        ->value();

                    $message->attachData($pdfBytes, $safeName, [
                        'mime' => 'application/pdf',
                    ]);
                }

                foreach ((array) ($agreement->attachments ?? []) as $relativePath) {
                    if (!$relativePath) {
                        continue;
                    }

                    $absolutePath = $this->resolveAgreementAttachmentAbsolutePath((string) $relativePath);

                    if (!$absolutePath) {
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

    protected function renderAgreementPdf(EventAgreement $agreement): ?string
    {
        if (blank($agreement->agreement_body)) {
            return null;
        }

        $html = view('pdf.agreement', [
            'agreement' => $agreement,
            'agreementBody' => (string) $agreement->agreement_body,
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->output();
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
