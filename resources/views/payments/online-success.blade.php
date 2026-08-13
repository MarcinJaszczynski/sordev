<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Płatność przyjęta</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 2rem 1rem; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; }
        .ok { color: #166534; }
    </style>
</head>
<body>
<div class="card">
    <h1 class="ok" style="margin-top:0">Płatność przyjęta — dziękujemy</h1>
    <p>{{ $session->description }} — {{ $amountLabel }}</p>
    <p style="color:#64748b;font-size:.875rem">Status sesji: {{ $session->status }}</p>
</div>
</body>
</html>
