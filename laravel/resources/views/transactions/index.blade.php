@extends('layouts.app')

@section('title', 'Transactions - ' . $taxYear->year . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/tax-years/' . $taxYear->year) }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $taxYear->year }}</a>
        <h1 class="text-2xl font-bold mt-2">Transactions — {{ $taxYear->year }}</h1>
    </div>

    {{-- Filters --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ url('/tax-years/' . $taxYear->year . '/transactions') }}"
           class="text-sm px-3 py-1 rounded {{ !$filter ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}">
            All ({{ $counts['total'] }})
        </a>
        <a href="{{ url('/tax-years/' . $taxYear->year . '/transactions?filter=matched') }}"
           class="text-sm px-3 py-1 rounded {{ $filter === 'matched' ? 'bg-stone-800 text-white' : 'bg-stone-100 text-stone-600 hover:bg-stone-200' }}">
            Matched ({{ $counts['matched'] }})
        </a>
        <a href="{{ url('/tax-years/' . $taxYear->year . '/transactions?filter=unmatched') }}"
           class="text-sm px-3 py-1 rounded {{ $filter === 'unmatched' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
            Unmatched ({{ $counts['unmatched'] }})
        </a>
    </div>

    @if($transactions->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No transactions found.</p>
        </div>
    @else
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 font-medium text-right">Amount</th>
                        <th class="px-4 py-3 font-medium">Buckets</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($transactions as $transaction)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $transaction->date->format('m/d/Y') }}</td>
                            <td class="px-4 py-3">{{ $transaction->description }}</td>
                            <td class="px-4 py-3 text-right whitespace-nowrap {{ $transaction->amount > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                ${{ number_format(abs($transaction->amount), 2) }}
                            </td>
                            <td class="px-4 py-3">
                                @if($transaction->buckets->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($transaction->buckets as $bucket)
                                            <span class="text-xs bg-stone-100 px-2 py-0.5 rounded">{{ $bucket->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-xs text-stone-400">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($transaction->match_type === 'auto')
                                    <span class="text-xs bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded">auto</span>
                                @elseif($transaction->match_type === 'manual')
                                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded">manual</span>
                                @else
                                    <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded">unmatched</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    @endif
@endsection