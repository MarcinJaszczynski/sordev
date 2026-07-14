<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Portal pilota' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; color: #111827; }
        .pilot-mobile-shell { max-width: 42rem; margin: 0 auto; min-height: 100vh; }
        .pilot-touch-btn { min-height: 2.75rem; padding: 0.75rem 1rem; font-weight: 600; border-radius: 0.75rem; }
        .pilot-field { width: 100%; border: 1px solid #d1d5db; border-radius: 0.75rem; padding: 0.75rem; font-size: 1rem; background: #fff; color: #111827; }
        .pilot-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 1rem; padding: 1rem; margin-bottom: 1rem; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    </style>
</head>
<body>
    <div class="pilot-mobile-shell p-4 pb-24">
        <header class="mb-4 flex items-center justify-between gap-3">
            <div>
                <a href="{{ url('/pilot') }}" class="text-sm text-teal-700 hover:underline">← Portal pilota</a>
                <h1 class="mt-1 text-lg font-bold text-gray-900">{{ $title ?? 'Rozliczenie' }}</h1>
            </div>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
