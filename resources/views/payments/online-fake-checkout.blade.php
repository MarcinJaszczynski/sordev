<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Symulator płatności</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 2rem 1rem; color: #0f172a; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 3px rgb(0 0 0 / 0.08); }
        .btn { display: inline-block; margin-top: 1rem; background: #2563eb; color: #fff; text-decoration: none; padding: 0.75rem 1.25rem; border-radius: 0.75rem; font-weight: 600; border: 0; cursor: pointer; }
        .muted { color: #64748b; font-size: 0.875rem; }
    </style>
</head>
<body>
<div class="card">
    <h1 style="margin:0 0 .5rem;font-size:1.25rem">Symulator bramki ({{ $session->driver }})</h1>
    <p class="muted">Środowisko deweloperskie — bez prawdziwego Tpay/PayU.</p>
    <p><strong>{{ $session->description }}</strong></p>
    <p>Kwota: <strong>{{ $amountLabel }}</strong></p>
    <form method="post" action="{{ route('payments.online.fake-pay', ['uuid' => $session->uuid]) }}">
        @csrf
        <button class="btn" type="submit">Zapłać (symulacja)</button>
    </form>
</div>
</body>
</html>
