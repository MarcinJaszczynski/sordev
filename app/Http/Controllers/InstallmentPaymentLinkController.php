<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContractPaymentSchedule;
use App\Models\EventAgreementPaymentSchedule;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class InstallmentPaymentLinkController extends Controller
{
    public function __invoke(Request $request, string $type, int $schedule): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $model = match ($type) {
            'contract' => ContractPaymentSchedule::query()->with(['contract.event'])->findOrFail($schedule),
            'agreement' => EventAgreementPaymentSchedule::query()->with(['eventAgreement.event'])->findOrFail($schedule),
            default => abort(404),
        };

        $amount = (float) ($model->amount ?? 0);
        $paid = (float) ($model->paid_amount ?? 0);
        $remaining = max(0, round($amount - $paid, 2));

        $event = $type === 'contract'
            ? $model->contract?->event
            : $model->eventAgreement?->event;

        $clientName = $type === 'contract'
            ? ($model->contract?->client_name ?? $event?->client_name)
            : ($model->eventAgreement?->client_name ?? $event?->client_name);

        $title = $type === 'contract'
            ? ($model->label ?? $model->title ?? 'Rata umowy')
            : ($model->label ?? $model->title ?? 'Rata umowy');

        $payOnlineUrl = $remaining > 0.009
            ? URL::temporarySignedRoute(
                'payments.installment.pay',
                now()->addDays(14),
                ['type' => $type, 'schedule' => $model->getKey()],
            )
            : null;

        return view('payments.installment-public', [
            'type' => $type,
            'schedule' => $model,
            'event' => $event,
            'title' => $title,
            'clientName' => $clientName ?: '—',
            'amountLabel' => MoneyFormatter::format($amount, 'PLN'),
            'paidLabel' => MoneyFormatter::format($paid, 'PLN'),
            'remainingLabel' => MoneyFormatter::format($remaining, 'PLN'),
            'dueDate' => $model->due_date?->format('d.m.Y') ?? '—',
            'isPaid' => $remaining <= 0.009,
            'transferTitle' => trim(($event?->code ? $event->code.' ' : '').'rata #'.$model->getKey()),
            'payOnlineUrl' => $payOnlineUrl,
            'paymentsDriver' => (string) config('payments.driver', 'fake'),
        ]);
    }
}
