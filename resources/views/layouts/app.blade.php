<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel')</title>
    <link rel="stylesheet" href="/css/filament/filament/app.css">
    @livewireStyles
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto py-8">
        @yield('content')
        {{ $slot ?? '' }}
    </div>
    @livewireScripts
    @stack('scripts')
</body>

</html>