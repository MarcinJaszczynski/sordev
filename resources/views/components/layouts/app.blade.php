<!DOCTYPE html>
<html lang="pl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel administracyjny')</title>
    <link rel="stylesheet" href="/css/app.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css">
    @livewireStyles
    <style>
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        }

        header {
            background: #1e293b;
            color: #fff;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        nav a {
            color: #fff;
            margin-right: 1.5rem;
            text-decoration: none;
            font-weight: 500;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1200px;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen">
    <header>
        <div class="container mx-auto flex justify-between items-center">
            <div class="text-xl font-bold"><i class="fa-solid fa-cubes"></i> Panel administracyjny</div>
            <nav>
                <a href="/admin">Dashboard</a>
                <a href="/admin/event-templates">Szablony wydarzeń</a>
                <a href="/admin/event-templates/create">Dodaj szablon</a>
            </nav>
        </div>
    </header>
    <main class="container mx-auto py-8">
        @yield('content')
        {{-- $slot usunięty, bo nie jest używany w tym kontekście --}}
    </main>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    @livewireScripts
    @stack('scripts')
</body>

</html>