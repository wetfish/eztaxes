@extends('layouts.app')

@section('title', 'Import ' . $asset->symbol . ' - Crypto - EzTaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/crypto/' . $asset->id) }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $asset->name }}</a>
        <h1 class="text-2xl font-bold mt-2">Import CSV — {{ $asset->name }} <span class="text-stone-400 font-normal">{{ $asset->symbol }}</span></h1>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
        <div class="bg-white border border-stone-200 rounded-lg p-5">
            <h3 class="font-medium mb-2">CashApp Format</h3>
            <p class="text-sm text-stone-500 mb-2">
                Transaction-level data with buy and sell rows. Sells are imported as unallocated — assign them to specific buys after import.
            </p>
            <div class="text-xs text-stone-400">
                Required columns: Date, Transaction Type, Asset Amount, Asset Price
            </div>
        </div>

        <div class="bg-white border border-stone-200 rounded-lg p-5">
            <h3 class="font-medium mb-2">Coinbase Gain/Loss Report</h3>
            <p class="text-sm text-stone-500 mb-2">
                Pre-calculated tax lot data with cost basis and proceeds already matched. Each row becomes a fully-allocated buy+sell pair.
            </p>
            <div class="text-xs text-stone-400">
                Required columns: Amount, Date of Disposition, Cost basis (USD), Proceeds (USD)
            </div>
        </div>
    </div>

    <div class="bg-white border border-stone-200 rounded-lg p-6 max-w-xl">
        <p class="text-sm text-stone-500 mb-4">
            Upload a CSV file and the importer will automatically detect the format. Header rows buried under
            disclaimers or metadata (like Coinbase reports) are handled automatically.
        </p>

        <form action="{{ url('/crypto/' . $asset->id . '/import') }}" method="POST" enctype="multipart/form-data">
            @csrf

            @if($errors->any())
                <div class="mb-4">
                    @foreach($errors->all() as $error)
                        <div class="text-red-500 text-sm">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="mb-6">
                <label for="csv_file" class="block text-sm font-medium mb-2">CSV File</label>
                <input
                    type="file"
                    name="csv_file"
                    id="csv_file"
                    accept=".csv,.txt"
                    required
                    class="block w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-stone-100 file:text-stone-700 hover:file:bg-stone-200"
                >
            </div>

            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Import
            </button>
        </form>
    </div>
@endsection