@extends('layouts.app')

@section('title', $taxYear->year . ' - EzTaxes')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-bold">Tax Year {{ $taxYear->year }}</h1>
            <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded
                {{ $taxYear->filing_status === 'filed' ? 'bg-emerald-100 text-emerald-700' : '' }}
                {{ $taxYear->filing_status === 'draft' ? 'bg-amber-100 text-amber-700' : '' }}
                {{ $taxYear->filing_status === 'amended' ? 'bg-blue-100 text-blue-700' : '' }}
            ">{{ ucfirst($taxYear->filing_status) }}</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ url('/tax-years/' . $taxYear->year . '/balance-sheet') }}" class="bg-white border border-stone-300 text-stone-700 px-4 py-2 rounded text-sm hover:bg-stone-50 transition-colors">
                Balance Sheet
            </a>
            <a href="{{ url('/import') }}" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Import CSV
            </a>
        </div>
    </div>

    {{-- Grand Totals (from cached transaction data) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Total Income</div>
            <div class="text-xl font-bold text-emerald-600">${{ number_format($taxYear->total_income, 2) }}</div>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Total Expenses</div>
            <div class="text-xl font-bold text-red-600">${{ number_format(abs($taxYear->total_expenses), 2) }}</div>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Net</div>
            <div class="text-xl font-bold">${{ number_format($taxYear->total_income + $taxYear->total_expenses, 2) }}</div>
        </div>
    </div>

    {{-- Group Breakdown --}}
    <h2 class="text-lg font-bold mb-4">Bucket Breakdown</h2>

    @if(collect($groupBreakdown)->isEmpty() && $unassignedBuckets->isEmpty())
        <div class="text-stone-400 text-sm mb-8">No categorized transactions yet.</div>
    @else
        @foreach($groupBreakdown as $entry)
            @php $group = $entry['group']; @endphp
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-stone-700">{{ $group->name }}</h3>
                    <div class="text-sm">
                        @if($entry['income'] > 0)
                            <span class="text-emerald-600">${{ number_format($entry['income'], 2) }}</span>
                        @endif
                        @if($entry['expenses'] < 0)
                            <span class="text-red-600 {{ $entry['income'] > 0 ? 'ml-4' : '' }}">${{ number_format(abs($entry['expenses']), 2) }}</span>
                        @endif
                    </div>
                </div>

                <div class="bg-white border border-stone-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="bg-stone-100 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Bucket</th>
                                <th class="px-4 py-3 font-medium text-right">Income</th>
                                <th class="px-4 py-3 font-medium text-right">Expenses</th>
                                <th class="px-4 py-3 font-medium text-right">Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($group->buckets as $bucket)
                                @php
                                    $bucketIncome = $bucket->transactions->where('amount', '>', 0)->sum('amount');
                                    $bucketExpenses = $bucket->transactions->where('amount', '<', 0)->sum('amount');
                                    $bucketCount = $bucket->transactions->count();
                                    $bucketId = 'bucket-' . $bucket->id;
                                @endphp
                                <tr class="border-t border-stone-100 cursor-pointer hover:bg-stone-50 {{ $bucket->behavior !== 'normal' ? 'text-stone-400' : '' }}"
                                    onclick="document.getElementById('{{ $bucketId }}').classList.toggle('hidden')">
                                    <td class="px-4 py-3">
                                        {{ $bucket->name }}
                                        @if($bucket->behavior !== 'normal')
                                            <span class="text-xs ml-1">[{{ $bucket->behavior }}]</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-emerald-600">
                                        ${{ number_format($bucketIncome, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-600">
                                        ${{ number_format(abs($bucketExpenses), 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ $bucketCount }}
                                    </td>
                                </tr>
                                <tr id="{{ $bucketId }}" class="hidden">
                                    <td colspan="4" class="px-0 py-0">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <tbody>
                                                @foreach($bucket->transactions->sortBy('date') as $transaction)
                                                    <tr class="{{ $loop->index % 2 === 0 ? 'bg-stone-50' : 'bg-white' }}">
                                                        <td class="px-6 py-1.5 text-stone-500 whitespace-nowrap w-28">{{ $transaction->date->format('m/d/Y') }}</td>
                                                        <td class="px-4 py-1.5 text-stone-700">{{ $transaction->description }}</td>
                                                        <td class="px-4 py-1.5 text-right whitespace-nowrap w-28 {{ $transaction->amount > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                            ${{ number_format(abs($transaction->amount), 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        {{-- Unassigned buckets --}}
        @if($unassignedBuckets->isNotEmpty())
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="font-medium text-amber-700">Unassigned Buckets</h3>
                    <div class="text-xs text-amber-600">
                        <a href="{{ url('/buckets') }}" class="hover:underline">Manage bucket groups &rarr;</a>
                    </div>
                </div>

                <div class="bg-white border border-amber-200 rounded-lg overflow-x-auto">
                    <table class="w-full text-sm whitespace-nowrap">
                        <thead class="bg-amber-50 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Bucket</th>
                                <th class="px-4 py-3 font-medium text-right">Income</th>
                                <th class="px-4 py-3 font-medium text-right">Expenses</th>
                                <th class="px-4 py-3 font-medium text-right">Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unassignedBuckets as $bucket)
                                @php
                                    $bucketIncome = $bucket->transactions->where('amount', '>', 0)->sum('amount');
                                    $bucketExpenses = $bucket->transactions->where('amount', '<', 0)->sum('amount');
                                    $bucketCount = $bucket->transactions->count();
                                    $bucketId = 'bucket-' . $bucket->id;
                                @endphp
                                <tr class="border-t border-amber-100 cursor-pointer hover:bg-amber-50 {{ $bucket->behavior !== 'normal' ? 'text-stone-400' : '' }}"
                                    onclick="document.getElementById('{{ $bucketId }}').classList.toggle('hidden')">
                                    <td class="px-4 py-3">
                                        {{ $bucket->name }}
                                        @if($bucket->behavior !== 'normal')
                                            <span class="text-xs ml-1">[{{ $bucket->behavior }}]</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-emerald-600">
                                        ${{ number_format($bucketIncome, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-red-600">
                                        ${{ number_format(abs($bucketExpenses), 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        {{ $bucketCount }}
                                    </td>
                                </tr>
                                <tr id="{{ $bucketId }}" class="hidden">
                                    <td colspan="4" class="px-0 py-0">
                                        <table class="w-full text-sm whitespace-nowrap">
                                            <tbody>
                                                @foreach($bucket->transactions->sortBy('date') as $transaction)
                                                    <tr class="{{ $loop->index % 2 === 0 ? 'bg-stone-50' : 'bg-white' }}">
                                                        <td class="px-6 py-1.5 text-stone-500 whitespace-nowrap w-28">{{ $transaction->date->format('m/d/Y') }}</td>
                                                        <td class="px-4 py-1.5 text-stone-700">{{ $transaction->description }}</td>
                                                        <td class="px-4 py-1.5 text-right whitespace-nowrap w-28 {{ $transaction->amount > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                                            ${{ number_format(abs($transaction->amount), 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    {{-- Unmatched --}}
    @if($unmatchedCount > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-8">
            <div class="font-medium text-amber-800">{{ $unmatchedCount }} unmatched transaction{{ $unmatchedCount > 1 ? 's' : '' }}</div>
            <a href="{{ url('/tax-years/' . $taxYear->year . '/transactions?filter=unmatched') }}" class="text-sm text-amber-600 hover:underline">Review &rarr;</a>
        </div>
    @endif

    {{-- Import History --}}
    <h2 class="text-lg font-bold mb-4">Import History</h2>

    @if($imports->isEmpty())
        <div class="text-stone-400 text-sm">No imports yet. Upload a CSV to get started.</div>
    @else
        <div class="bg-white border border-stone-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">File</th>
                        <th class="px-4 py-3 font-medium text-right">Total</th>
                        <th class="px-4 py-3 font-medium text-right">Matched</th>
                        <th class="px-4 py-3 font-medium text-right">Unmatched</th>
                        <th class="px-4 py-3 font-medium text-right">Ignored</th>
                        <th class="px-4 py-3 font-medium text-right">Imported</th>
                        <th class="px-4 py-3 font-medium text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($imports as $import)
                        <tr>
                            <td class="px-4 py-3">{{ $import->original_filename }}</td>
                            <td class="px-4 py-3 text-right">{{ $import->rows_total }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">{{ $import->rows_matched }}</td>
                            <td class="px-4 py-3 text-right text-amber-600">{{ $import->rows_unmatched }}</td>
                            <td class="px-4 py-3 text-right text-stone-400">{{ $import->rows_ignored }}</td>
                            <td class="px-4 py-3 text-right text-stone-500">{{ $import->imported_at->format('M j, Y g:ia') }}</td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ url('/imports/' . $import->id) }}" method="POST" onsubmit="return confirm('Delete this import and all its transactions?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection