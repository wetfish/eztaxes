<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'EzTaxes')</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-stone-50 text-stone-800 min-h-screen flex flex-col">

    {{-- Navigation --}}
    <nav class="bg-stone-900 text-stone-100">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-tight">EzTaxes</a>

            {{-- Desktop nav --}}
            <div class="hidden md:flex items-center gap-6 text-sm">
                <a href="{{ url('/') }}" class="hover:text-white transition-colors {{ request()->is('/') ? 'text-white' : 'text-stone-400' }}">Tax Years</a>
                <a href="{{ url('/payroll') }}" class="hover:text-white transition-colors {{ request()->is('payroll*') ? 'text-white' : 'text-stone-400' }}">Payroll</a>
                <a href="{{ url('/crypto') }}" class="hover:text-white transition-colors {{ request()->is('crypto*') ? 'text-white' : 'text-stone-400' }}">Crypto</a>
                <a href="{{ url('/buckets') }}" class="hover:text-white transition-colors {{ request()->is('buckets*') ? 'text-white' : 'text-stone-400' }}">Buckets</a>
                <a href="{{ url('/csv-templates') }}" class="hover:text-white transition-colors {{ request()->is('csv-templates*') ? 'text-white' : 'text-stone-400' }}">CSV Templates</a>
                <a href="{{ url('/import') }}" class="ml-2 bg-stone-700 hover:bg-stone-600 text-white px-3 py-1.5 rounded transition-colors {{ request()->is('import*') ? 'bg-stone-600' : '' }}">Import CSV</a>
            </div>

            {{-- Mobile hamburger button --}}
            <button id="mobile-menu-btn" class="md:hidden text-stone-300 hover:text-white p-1" aria-label="Open menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </nav>

    {{-- Mobile menu overlay --}}
    <div id="mobile-menu" class="fixed inset-0 bg-stone-900 z-50 hidden flex-col">
        <div class="flex items-center justify-between px-6 py-4">
            <a href="{{ url('/') }}" class="text-lg font-bold tracking-tight text-white">EzTaxes</a>
            <button id="mobile-menu-close" class="text-stone-300 hover:text-white p-1" aria-label="Close menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="flex flex-col px-6 py-4 gap-1">
            <a href="{{ url('/') }}" class="py-3 px-4 rounded text-lg {{ request()->is('/') ? 'text-white bg-stone-800' : 'text-stone-300 hover:text-white hover:bg-stone-800' }} transition-colors">Tax Years</a>
            <a href="{{ url('/payroll') }}" class="py-3 px-4 rounded text-lg {{ request()->is('payroll*') ? 'text-white bg-stone-800' : 'text-stone-300 hover:text-white hover:bg-stone-800' }} transition-colors">Payroll</a>
            <a href="{{ url('/crypto') }}" class="py-3 px-4 rounded text-lg {{ request()->is('crypto*') ? 'text-white bg-stone-800' : 'text-stone-300 hover:text-white hover:bg-stone-800' }} transition-colors">Crypto</a>
            <a href="{{ url('/buckets') }}" class="py-3 px-4 rounded text-lg {{ request()->is('buckets*') ? 'text-white bg-stone-800' : 'text-stone-300 hover:text-white hover:bg-stone-800' }} transition-colors">Buckets</a>
            <a href="{{ url('/csv-templates') }}" class="py-3 px-4 rounded text-lg {{ request()->is('csv-templates*') ? 'text-white bg-stone-800' : 'text-stone-300 hover:text-white hover:bg-stone-800' }} transition-colors">CSV Templates</a>
            <a href="{{ url('/import') }}" class="py-3 px-4 mt-2 rounded text-lg text-center bg-stone-700 hover:bg-stone-600 text-white transition-colors">Import CSV</a>
        </div>
    </div>

    <script>
        const menuBtn = document.getElementById('mobile-menu-btn');
        const menuClose = document.getElementById('mobile-menu-close');
        const menu = document.getElementById('mobile-menu');

        menuBtn.addEventListener('click', function() {
            menu.classList.remove('hidden');
            menu.classList.add('flex');
        });

        menuClose.addEventListener('click', function() {
            menu.classList.add('hidden');
            menu.classList.remove('flex');
        });
    </script>

    {{-- Demo Mode Banner --}}
    @if(config('demo.enabled'))
        <div class="bg-amber-500 text-white text-center text-sm font-medium py-2 px-4">
            ⚠️ Demo Mode &mdash; This is fictional data for demonstration only. All forms are read-only. <a href="https://github.com/wetfish/eztaxes" class="underline font-bold hover:text-amber-100">Download EzTaxes</a> to use with your own data.
        </div>
    @endif

    {{-- Flash Messages --}}
    @if(session('demo_blocked'))
        <div class="max-w-6xl mx-auto px-6 mt-4">
            <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded">
                {{ session('demo_blocked') }}
            </div>
        </div>
    @endif

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
        EzTaxes &mdash; S-Corp Tax Management
    </footer>

</body>
</html>