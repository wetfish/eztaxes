@extends('layouts.app')

@section('title', 'Confirm Import - EzTaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/import') }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to upload</a>
        <h1 class="text-2xl font-bold mt-2">Confirm Import</h1>
        <p class="text-sm text-stone-500 mt-1">{{ $filename }} &mdash; {{ $rowCount }} data rows detected</p>
    </div>

    {{-- Detected Template Banner --}}
    @if($detectedTemplate)
        <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-5 py-4 mb-6 flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>
                <p class="font-medium text-emerald-900">Detected: {{ $detectedTemplate->name }}</p>
                @if($importModule === 'payroll')
                    <p class="text-sm text-emerald-700 mt-0.5">This will be imported into the Payroll module.</p>
                @elseif($importModule === 'crypto')
                    <p class="text-sm text-emerald-700 mt-0.5">This will be imported into the Crypto module. Select the asset below.</p>
                @else
                    <p class="text-sm text-emerald-700 mt-0.5">Columns have been mapped automatically.</p>
                @endif
            </div>
        </div>
    @else
        @if($headerRowIndex > 0)
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-5 py-4 mb-6">
                <p class="text-sm text-amber-800">{{ $headerRowIndex }} preamble row(s) detected and skipped. Please verify the settings below.</p>
            </div>
        @endif
    @endif

    <form action="{{ url('/import/process') }}" method="POST">
        @csrf
        <input type="hidden" name="stored_file" value="{{ $storedFile }}">
        <input type="hidden" name="filename" value="{{ $filename }}">
        <input type="hidden" name="header_row_index" value="{{ $headerRowIndex }}">
        <input type="hidden" name="import_module" value="{{ $importModule }}">
        @if($detectedTemplate)
            <input type="hidden" name="detected_template_id" value="{{ $detectedTemplate->id }}">
        @endif

        {{-- Crypto Asset Selection (crypto imports only) --}}
        @if($importModule === 'crypto')
            <div class="bg-white border border-stone-200 rounded-lg p-6 mb-6 max-w-xl">
                <h2 class="font-medium mb-4">Crypto asset</h2>

                @if($detectedAssetSymbol)
                    <p class="text-sm text-stone-500 mb-3">
                        Detected <strong>{{ $detectedAssetSymbol }}</strong> from the file.
                    </p>
                @endif

                <select name="crypto_asset_id" id="crypto_asset_id" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required onchange="toggleNewAssetFields(this)">
                    @foreach($cryptoAssets as $asset)
                        <option value="{{ $asset->id }}" {{ $detectedAssetSymbol && strtoupper($asset->symbol) === strtoupper($detectedAssetSymbol) ? 'selected' : '' }}>
                            {{ $asset->name }} ({{ $asset->symbol }})
                        </option>
                    @endforeach
                    <option value="new">+ Create new asset</option>
                </select>

                <div id="new_asset_fields" class="grid gap-3 mt-4" style="display: none;">
                    <div>
                        <label for="new_asset_name" class="block text-sm font-medium mb-1">Asset name</label>
                        <input type="text" name="new_asset_name" id="new_asset_name" placeholder="e.g. Bitcoin" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                    </div>
                    <div>
                        <label for="new_asset_symbol" class="block text-sm font-medium mb-1">Symbol</label>
                        <input type="text" name="new_asset_symbol" id="new_asset_symbol" placeholder="e.g. BTC" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                    </div>
                </div>
            </div>
        @endif

        {{-- Tax Year Selection (bank and payroll only) --}}
        @if($importModule !== 'crypto')
            <div class="bg-white border border-stone-200 rounded-lg p-6 mb-6 max-w-xl">
                <h2 class="font-medium mb-4">Tax year</h2>

                @if($detectedYear)
                    <p class="text-sm text-stone-500 mb-3">
                        Detected <strong>{{ $detectedYear }}</strong> from the file.
                    </p>
                @endif

                <div class="flex items-center gap-4">
                    <select name="tax_year" id="tax_year" class="border border-stone-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                        @if($detectedYear && !$taxYears->contains('year', $detectedYear))
                            <option value="{{ $detectedYear }}" selected>{{ $detectedYear }} (new)</option>
                        @endif
                        @foreach($taxYears as $ty)
                            <option value="{{ $ty->year }}" {{ $detectedYear == $ty->year ? 'selected' : '' }}>
                                {{ $ty->year }}
                            </option>
                        @endforeach
                        @if(!$detectedYear && $taxYears->isEmpty())
                            <option value="{{ date('Y') }}" selected>{{ date('Y') }} (new)</option>
                        @endif
                    </select>

                    <span class="text-stone-400 text-sm">or</span>

                    <input
                        type="number"
                        id="new_year_input"
                        placeholder="New year"
                        min="2000"
                        max="2099"
                        class="border border-stone-300 rounded px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-stone-400"
                    >
                    <button type="button" id="add_year_btn" class="text-sm text-stone-600 hover:text-stone-800 underline">Add</button>
                </div>
            </div>
        @endif

        {{-- Column Mapping (bank imports only) --}}
        @if($importModule === 'bank')
            <div class="bg-white border border-stone-200 rounded-lg p-6 mb-6 max-w-xl">
                <h2 class="font-medium mb-4">Column mapping</h2>

                <div class="grid gap-4">
                    <div>
                        <label for="col_date" class="block text-sm font-medium mb-1">Date column</label>
                        <select name="col_date" id="col_date" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                            <option value="-1" {{ ($mappedColumns['date'] ?? -1) == -1 ? 'selected' : '' }}>
                                No date column (use Dec 31)
                            </option>
                            @foreach($headers as $index => $header)
                                <option value="{{ $index }}" {{ ($mappedColumns['date'] ?? -1) == $index ? 'selected' : '' }}>
                                    {{ $header }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="col_amount" class="block text-sm font-medium mb-1">Amount column</label>
                        <select name="col_amount" id="col_amount" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                            @foreach($headers as $index => $header)
                                <option value="{{ $index }}" {{ ($mappedColumns['amount'] ?? 0) == $index ? 'selected' : '' }}>
                                    {{ $header }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="col_description" class="block text-sm font-medium mb-1">Description column</label>
                        <select name="col_description" id="col_description" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                            @foreach($headers as $index => $header)
                                <option value="{{ $index }}" {{ ($mappedColumns['description'] ?? 0) == $index ? 'selected' : '' }}>
                                    {{ $header }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        @endif

        {{-- Preview --}}
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-6">
            <h2 class="font-medium px-4 py-3 bg-stone-50 border-b border-stone-200">Preview (first {{ count($preview) }} rows)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-100 text-left">
                        <tr>
                            @foreach($headers as $header)
                                <th class="px-4 py-2 font-medium whitespace-nowrap">{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach($preview as $row)
                            <tr>
                                @foreach($headers as $index => $header)
                                    <td class="px-4 py-2 whitespace-nowrap">{{ $row[$index] ?? '' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Save Template Option (bank imports with unknown format only) --}}
        @if($importModule === 'bank' && !$detectedTemplate)
            <div class="bg-white border border-stone-200 rounded-lg p-6 mb-6 max-w-xl">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="save_template" value="1" class="rounded border-stone-300">
                    Save this column mapping as a reusable template
                </label>
                <input
                    type="text"
                    name="template_name"
                    placeholder="Template name, e.g. Local Credit Union Checking"
                    class="border border-stone-300 rounded px-3 py-2 text-sm w-full mt-3 focus:outline-none focus:ring-2 focus:ring-stone-400"
                >
            </div>
        @endif

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                @if($importModule === 'payroll')
                    Import {{ $rowCount }} Payroll Entries
                @elseif($importModule === 'crypto')
                    Import Crypto Transactions
                @else
                    Import {{ $rowCount }} Transactions
                @endif
            </button>
            <a href="{{ url('/import') }}" class="text-sm text-stone-500 hover:text-stone-700">Cancel</a>
        </div>
    </form>

    <script>
        // New year input for tax year
        const addYearBtn = document.getElementById('add_year_btn');
        if (addYearBtn) {
            addYearBtn.addEventListener('click', function() {
                const input = document.getElementById('new_year_input');
                const select = document.getElementById('tax_year');
                const year = parseInt(input.value);

                if (year >= 2000 && year <= 2099) {
                    for (let opt of select.options) {
                        if (parseInt(opt.value) === year) {
                            opt.selected = true;
                            input.value = '';
                            return;
                        }
                    }

                    const option = new Option(year + ' (new)', year, true, true);
                    select.insertBefore(option, select.firstChild);
                    input.value = '';
                }
            });
        }

        // Crypto asset "create new" toggle
        function toggleNewAssetFields(select) {
            const fields = document.getElementById('new_asset_fields');
            if (fields) {
                fields.style.display = select.value === 'new' ? 'grid' : 'none';
            }
        }
    </script>
@endsection