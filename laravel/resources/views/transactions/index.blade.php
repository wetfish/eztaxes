@extends('layouts.app')

@section('title', 'Transactions - ' . $taxYear->year . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/tax-years/' . $taxYear->year) }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $taxYear->year }}</a>
        <h1 class="text-2xl font-bold mt-2">Transactions — {{ $taxYear->year }}</h1>
    </div>

    {{-- Pattern Builder --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-6">
        <h2 class="font-medium mb-3">Pattern Builder</h2>

        {{-- Test pattern --}}
        <form method="GET" action="{{ url('/tax-years/' . $taxYear->year . '/transactions') }}" class="flex items-end gap-3 mb-3" id="pattern-test-form">
            @if($filter)
                <input type="hidden" name="filter" value="{{ $filter }}">
            @endif
            <div class="flex-1">
                <label class="block text-xs font-medium text-stone-500 mb-1">Test regex pattern</label>
                <input
                    type="text"
                    name="pattern"
                    id="pattern-input"
                    value="{{ $testPattern }}"
                    placeholder="Type a regex pattern to filter transactions..."
                    class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400 font-mono"
                >
            </div>
            <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors whitespace-nowrap">
                Test
            </button>
            @if($testPattern)
                <a href="{{ url('/tax-years/' . $taxYear->year . '/transactions' . ($filter ? '?filter=' . $filter : '')) }}" class="text-sm text-stone-500 hover:text-stone-700 whitespace-nowrap">
                    Clear
                </a>
            @endif
        </form>

        {{-- Pattern match results and save --}}
        @if($testPattern)
            <div class="flex items-center justify-between border-t border-stone-100 pt-3">
                <div class="text-sm">
                    @if($patternMatchCount !== null)
                        <span class="font-medium {{ $patternMatchCount > 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $patternMatchCount }} transaction{{ $patternMatchCount !== 1 ? 's' : '' }} match{{ $patternMatchCount === 1 ? 'es' : '' }}
                        </span>
                        <span class="text-stone-400 ml-2">
                            for pattern <code class="bg-stone-50 border border-stone-200 px-1.5 py-0.5 rounded">{{ $testPattern }}</code>
                        </span>
                    @endif
                </div>

                @if($patternMatchCount > 0)
                    <form action="{{ url('/transactions/create-pattern') }}" method="POST" class="flex items-center gap-3">
                        @csrf
                        <input type="hidden" name="pattern" value="{{ $testPattern }}">
                        <input type="hidden" name="tax_year_id" value="{{ $taxYear->id }}">
                        <select name="bucket_id" required class="border border-stone-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
                            <option value="">Save to bucket...</option>
                            @foreach($buckets as $bucket)
                                <option value="{{ $bucket->id }}">{{ $bucket->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="bg-emerald-600 text-white px-4 py-1.5 rounded text-sm hover:bg-emerald-500 transition-colors whitespace-nowrap">
                            Save Pattern
                        </button>
                    </form>
                @endif
            </div>
        @endif
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
            @if($testPattern)
                <p class="text-lg">No transactions match this pattern.</p>
            @else
                <p class="text-lg">No transactions found.</p>
            @endif
        </div>
    @else
        <div class="grid gap-3">
            @foreach($transactions as $transaction)
                <div class="bg-white border border-stone-200 rounded-lg">
                    <div class="flex items-center justify-between px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="text-sm text-stone-500 whitespace-nowrap">{{ $transaction->date->format('m/d/Y') }}</span>
                                <span class="text-sm font-medium whitespace-nowrap {{ $transaction->amount > 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    ${{ number_format(abs($transaction->amount), 2) }}
                                </span>
                                @if($transaction->match_type === 'auto')
                                    <span class="text-xs bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded">auto</span>
                                @elseif($transaction->match_type === 'manual')
                                    <span class="text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded">manual</span>
                                @else
                                    <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded">unmatched</span>
                                @endif
                            </div>
                            <div class="text-sm text-stone-700">{{ $transaction->description }}</div>
                            @if($transaction->buckets->isNotEmpty())
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($transaction->buckets as $bucket)
                                        <span class="text-xs bg-stone-100 px-2 py-0.5 rounded">{{ $bucket->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Action buttons --}}
                        @if($transaction->match_type === 'unmatched')
                            <div class="flex items-center gap-2 ml-4">
                                <button
                                    type="button"
                                    onclick="fillPattern('{{ addslashes(preg_quote($transaction->description, '/')) }}')"
                                    class="text-xs text-blue-600 hover:text-blue-800 whitespace-nowrap"
                                >
                                    Create pattern
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Quick assign for unmatched --}}
                    @if($transaction->match_type === 'unmatched')
                        <details class="border-t border-stone-100">
                            <summary class="px-4 py-2 text-xs text-stone-400 cursor-pointer hover:text-stone-600 select-none">
                                Quick assign to bucket...
                            </summary>
                            <div class="px-4 pb-3 pt-1">
                                <form action="{{ url('/transactions/' . $transaction->id . '/assign-bucket') }}" method="POST" class="flex items-center gap-3">
                                    @csrf
                                    <select name="bucket_id" required class="border border-stone-300 rounded px-3 py-1.5 text-sm flex-1 focus:outline-none focus:ring-2 focus:ring-stone-400">
                                        <option value="">Select bucket...</option>
                                        @foreach($buckets as $bucket)
                                            <option value="{{ $bucket->id }}">{{ $bucket->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="bg-stone-800 text-white px-4 py-1.5 rounded text-sm hover:bg-stone-700 transition-colors whitespace-nowrap">
                                        Assign
                                    </button>
                                </form>
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    @endif

    <script>
        function fillPattern(escapedDescription) {
            var input = document.getElementById('pattern-input');
            input.value = escapedDescription;
            input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            document.getElementById('pattern-test-form').submit();
        }
    </script>
@endsection