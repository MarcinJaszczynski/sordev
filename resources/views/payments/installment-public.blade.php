<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Płatność raty — {{ $title }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; padding: 2rem 1rem; }
        .card { max-width: 32rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 3px rgb(0 0 0 / 0.08); }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        .muted { color: #64748b; font-size: 0.875rem; }
        .row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; }
        .row:last-child { border-bottom: 0; }
        .badge { display: inline-block; margin-top: 1rem; padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .ok { background: #dcfce7; color: #166534; }
        .wait { background: #ffedd5; color: #9a3412; }
        .hint { margin-top: 1.25rem; padding: 1rem; background: #f1f5f9; border-radius: 0.75rem; font-size: 0.875rem; }
        code { font-size: 0.85rem; word-break: break-all; }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ $title }}</h1>
    <p class="muted">
        @if($event)
            Impreza: {{ $event->name }} @if($event->code) [{{ $event->code }}] @endif
        @else
            Płatność raty
        @endif
    </p>

    <div style="margin-top: 1.25rem">
        <div class="row"><span class="muted">Płatnik</span><strong>{{ $clientName }}</strong></div>
        <div class="row"><span class="muted">Termin</span><strong>{{ $dueDate }}</strong></div>
        <div class="row"><span class="muted">Kwota raty</span><strong>{{ $amountLabel }}</strong></div>
        <div class="row"><span class="muted">Wpłacono</span><strong>{{ $paidLabel }}</strong></div>
        <div class="row"><span class="muted">Do zapłaty</span><strong>{{ $remainingLabel }}</strong></div>
    </div>

    @if($isPaid)
        <span class="badge ok">Opłacone</span>
    @else
        <span class="badge wait">Oczekuje na wpłatę</span>
        @if(!empty($payOnlineUrl))
            <p style="margin-top:1.25rem">
                <a href="{{ $payOnlineUrl }}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:.75rem 1.25rem;border-radius:.75rem;font-weight:600">
                    {{ \App\Support\PaymentCta::label(includeSimulatorHint: true) }}
                </a>
            </p>
        @endif
        <div class="hint">
            <p><strong>Przelew tradycyjny</strong> — alternatywa dla płatności online.</p>
            <p>Tytuł przelewu: <code>{{ $transferTitle }}</code></p>
            <p class="muted" style="margin-top:0.75rem">Po zaksięgowaniu biuro oznaczy ratę jako opłaconą. Link jest podpisany czasowo.</p>
        </div>
    @endif
</div>
</body>
</html>
