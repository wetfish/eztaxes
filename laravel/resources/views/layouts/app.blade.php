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
            <div class="flex items-center gap-6 text-sm">
                <a href="{{ url('/') }}" class="hover:text-white transition-colors {{ request()->is('/') ? 'text-white' : 'text-stone-400' }}">Tax Years</a>
                <a href="{{ url('/payroll') }}" class="hover:text-white transition-colors {{ request()->is('payroll*') ? 'text-white' : 'text-stone-400' }}">Payroll</a>
                <a href="{{ url('/crypto') }}" class="hover:text-white transition-colors {{ request()->is('crypto*') ? 'text-white' : 'text-stone-400' }}">Crypto</a>
                <a href="{{ url('/buckets') }}" class="hover:text-white transition-colors {{ request()->is('buckets*') ? 'text-white' : 'text-stone-400' }}">Buckets</a>
                <a href="{{ url('/csv-templates') }}" class="hover:text-white transition-colors {{ request()->is('csv-templates*') ? 'text-white' : 'text-stone-400' }}">CSV Templates</a>
                <a href="{{ url('/import') }}" class="ml-2 bg-stone-700 hover:bg-stone-600 text-white px-3 py-1.5 rounded transition-colors {{ request()->is('import*') ? 'bg-stone-600' : '' }}">Import CSV</a>
            </div>
        </div>
    </nav>

    {{-- Demo Mode Banner --}}
    @if(config('demo.enabled'))
        <div class="bg-amber-500 text-white text-center text-sm font-medium py-2 px-4">
            ⚠️ Demo Mode &mdash; This data is fictional and for demonstration purposes only. Do not use for financial decisions.
        </div>
    @endif

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
        EzTaxes &mdash; S-Corp Tax Management
    </footer>

</body>
</html>