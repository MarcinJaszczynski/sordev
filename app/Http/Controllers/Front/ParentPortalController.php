<?php

declare(strict_types=1);

namespace App\Http\Controllers\Front;

use App\Actions\Finance\InitiateOnlinePaymentAction;
use App\Http\Controllers\Controller;
use App\Services\ParentParticipantAccessService;
use App\Support\EventParticipantConsents;
use App\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ParentPortalController extends Controller
{
    public function __construct(
        private readonly ParentParticipantAccessService $access,
    ) {}

    public function show(string $token): View
    {
        $participant = $this->access->findByToken($token);
        abort_unless($participant, 404);

        $payment = $participant->participantPayment;
        $due = round((float) ($payment?->due_amount_pln ?? 0), 2);
        $paid = round((float) ($payment?->paid_amount_pln ?? 0), 2);
        $remaining = max(0, round($due - $paid, 2));
        $schedule = $this->access->nextPayableSchedule($participant);

        return view('front.parent.portal', [
            'participant' => $participant,
            'event' => $participant->event,
            'token' => $token,
            'checklist' => EventParticipantConsents::checklist($participant->consents),
            'labels' => EventParticipantConsents::labels(),
            'requiredKeys' => EventParticipantConsents::requiredKeys(),
            'dueLabel' => MoneyFormatter::format($due, 'PLN'),
            'paidLabel' => MoneyFormatter::format($paid, 'PLN'),
            'remainingLabel' => MoneyFormatter::format($remaining, 'PLN'),
            'remaining' => $remaining,
            'canPay' => $schedule !== null && $remaining > 0.01,
            'nextInstallmentLabel' => $schedule
                ? (($schedule->label ?? 'Rata').' — '.MoneyFormatter::format(
                    max(0, (float) $schedule->amount - (float) ($schedule->paid_amount ?? 0)),
                    'PLN'
                ))
                : null,
        ]);
    }

    public function storeConsents(Request $request, string $token): RedirectResponse
    {
        $participant = $this->access->findByToken($token);
        abort_unless($participant, 404);

        $rules = [];
        foreach (EventParticipantConsents::requiredKeys() as $key) {
            $rules['consent_'.$key] = ['accepted'];
        }

        $request->validate($rules, [
            'consent_terms.accepted' => 'Zaakceptuj regulamin / warunki udziału.',
            'consent_insurance.accepted' => 'Zaakceptuj warunki ubezpieczenia.',
            'consent_rodo.accepted' => 'Zaakceptuj przetwarzanie danych (RODO).',
        ]);

        $flags = [];
        foreach (EventParticipantConsents::allKeys() as $key) {
            $flags[$key] = $request->boolean('consent_'.$key);
        }

        $payload = [];
        if (Schema::hasColumn('event_participants', 'consents')) {
            $payload['consents'] = EventParticipantConsents::applyFlags($flags, $participant->consents);
        }

        if (Schema::hasColumn('event_participants', 'parent_consent_at')
            && EventParticipantConsents::hasRequired($payload['consents'] ?? [])
        ) {
            $payload['parent_consent_at'] = $participant->parent_consent_at ?? now();
            if (Schema::hasColumn('event_participants', 'parent_consent_ip')) {
                $payload['parent_consent_ip'] = $request->ip();
            }
        }

        $participant->forceFill($payload)->save();

        return redirect()
            ->route('parent.portal.show', ['token' => $token])
            ->with('success', 'Zgody zapisane.');
    }

    public function pay(string $token): RedirectResponse
    {
        $participant = $this->access->findByToken($token);
        abort_unless($participant, 404);

        $schedule = $this->access->nextPayableSchedule($participant);
        if (! $schedule) {
            return redirect()
                ->route('parent.portal.show', ['token' => $token])
                ->with('info', 'Brak raty do opłacenia.');
        }

        $email = $participant->email
            ?: $participant->contract?->signer_email
            ?: $participant->contract?->customer_email
            ?: $participant->eventAgreement?->signer_email;

        $result = app(InitiateOnlinePaymentAction::class)($schedule, $email);

        return redirect()->away($result['checkout_url']);
    }
}
