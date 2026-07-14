<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalacja — SOR</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: #f1f5f9; color: #0f172a; line-height: 1.5; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 2rem 1rem 4rem; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); padding: 1.75rem; }
        h1 { font-size: 1.5rem; margin: 0 0 .25rem; }
        .sub { color: #64748b; margin-bottom: 1.5rem; font-size: .95rem; }
        .steps { display: flex; gap: .5rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .step { font-size: .75rem; padding: .25rem .6rem; border-radius: 999px; background: #e2e8f0; color: #475569; }
        .step.active { background: #2563eb; color: #fff; }
        .step.done { background: #dcfce7; color: #166534; }
        label { display: block; font-weight: 600; font-size: .875rem; margin-bottom: .35rem; }
        input[type=text], input[type=email], input[type=password], input[type=url] {
            width: 100%; padding: .6rem .75rem; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 1rem; font-size: 1rem;
        }
        .btn { display: inline-block; background: #2563eb; color: #fff; border: none; padding: .65rem 1.25rem; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: .95rem; }
        .btn:hover { background: #1d4ed8; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        .req-list { list-style: none; padding: 0; margin: 0 0 1rem; }
        .req-list li { padding: .5rem 0; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; font-size: .9rem; }
        .ok { color: #16a34a; font-weight: 600; }
        .fail { color: #dc2626; font-weight: 600; }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .9rem; }
        .hint { font-size: .85rem; color: #64748b; margin-top: -0.5rem; margin-bottom: 1rem; }
        .actions { display: flex; gap: .75rem; align-items: center; margin-top: .5rem; flex-wrap: wrap; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            @yield('content')
        </div>
    </div>
</body>
</html>
