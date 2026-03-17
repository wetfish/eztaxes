<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'eztaxes')</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-stone-50 text-stone-800 min-h-screen flex flex-col">

    {{-- Navigation --}}
    <nav class="bg-stone-900 text-stone-100">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-tight">eztaxes</a>
            <div class="flex items-center gap-6 text-sm">
                <a href="{{ url('/') }}" class="hover:text-white transition-colors {{ request()->is('/') ? 'text-white' : 'text-stone-400' }}">Dashboard</a>
                <a href="{{ url('/buckets') }}" class="hover:text-white transition-colors {{ request()->is('buckets*') ? 'text-white' : 'text-stone-400' }}">Buckets</a>
                <a href="{{ url('/csv-templates') }}" class="hover:text-white transition-colors {{ request()->is('csv-templates*') ? 'text-white' : 'text-stone-400' }}">CSV Templates</a>
            </div>
        </div>
    </nav>

    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="max-w-6xl mx-auto px-6 mt-4">
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded">
                {{ session('success') }}
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="max-w-6xl mx-auto px-6 mt-4">
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded">
                {{ session('error') }}
            </div>
        </div>
    @endif

    {{-- Page Content --}}
    <main class="flex-1 max-w-6xl mx-auto px-6 py-8 w-full">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="text-center text-xs text-stone-400 py-6">
        eztaxes &mdash; S-Corp Tax Management
    </footer>

</body>
</html>