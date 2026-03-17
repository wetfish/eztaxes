@extends('layouts.app')

@section('title', 'Copy Balance Sheet - ' . $taxYear->year . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/tax-years/' . $taxYear->year . '/balance-sheet') }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $taxYear->year }} Balance Sheet</a>
        <h1 class="text-2xl font-bold mt-2">Copy Balance Sheet from {{ $previousYear->year }} → {{ $taxYear->year }}</h1>
        <p class="text-sm text-stone-500 mt-1">Review and adjust quantities based on {{ $taxYear->year }} activity. Enter Dec 31, {{ $taxYear->year }} prices to calculate new values.</p>
    </div>

    <form action="{{ url('/tax-years/' . $taxYear->year . '/balance-sheet/copy') }}" method="POST">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                @foreach($errors->all() as $error)
                    <div class="text-red-600 text-sm">{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-4 mb-6">
            @foreach($adjustedItems as $index => $item)
                <div class="bg-white border border-stone-200 rounded-lg p-5">
                    <div class="flex items-center gap-3 mb-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="items[{{ $index }}][include]" value="1" checked class="rounded border-stone-300">
                            <span class="font-medium">{{ $item['label'] }}</span>
                        </label>
                        <span class="text-xs px-2 py-0.5 rounded
                            {{ $item['asset_type'] === 'crypto' ? 'bg-purple-50 text-purple-600' : '' }}
                            {{ $item['asset_type'] === 'stock' ? 'bg-blue-50 text-blue-600' : '' }}
                            {{ $item['asset_type'] === 'cash' ? 'bg-emerald-50 text-emerald-600' : '' }}
                            {{ $item['asset_type'] === 'other' ? 'bg-stone-100 text-stone-600' : '' }}
                        ">{{ ucfirst($item['asset_type']) }}</span>

                        <input type="hidden" name="items[{{ $index }}][label]" value="{{ $item['label'] }}">
                        <input type="hidden" name="items[{{ $index }}][asset_type]" value="{{ $item['asset_type'] }}">
                        <input type="hidden" name="items[{{ $index }}][crypto_asset_id]" value="{{ $item['crypto_asset_id'] }}">
                    </div>

                    {{-- Previous year summary --}}
                    <div class="bg-stone-50 rounded p-3 mb-4 text-sm">
                        <div class="text-xs font-medium text-stone-500 uppercase tracking-wider mb-2">{{ $previousYear->year }} Values</div>
                        <div class="flex gap-6">
                            @if($item['previous_quantity'])
                                <div>
                                    <span class="text-stone-400">Quantity:</span>
                                    <span class="font-mono">{{ rtrim(rtrim(number_format($item['previous_quantity'], 8), '0'), '.') }}</span>
                                </div>
                            @endif
                            @if($item['previous_unit_price'])
                                <div>
                                    <span class="text-stone-400">Price:</span>
                                    ${{ number_format($item['previous_unit_price'], 2) }}
                                </div>
                            @endif
                            <div>
                                <span class="text-stone-400">Total:</span>
                                <span class="font-medium">${{ number_format($item['previous_total_value'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Crypto activity for this year --}}
                    @if($item['crypto_asset_id'] && ($item['crypto_bought'] > 0 || $item['crypto_sold'] > 0))
                        <div class="bg-purple-50 rounded p-3 mb-4 text-sm">
                            <div class="text-xs font-medium text-purple-500 uppercase tracking-wider mb-2">{{ $taxYear->year }} Tracked Activity</div>
                            <div class="flex gap-6">
                                @if($item['crypto_bought'] > 0)
                                    <div class="text-emerald-600">
                                        Bought: +{{ rtrim(rtrim(number_format($item['crypto_bought'], 8), '0'), '.') }}
                                    </div>
                                @endif
                                @if($item['crypto_sold'] > 0)
                                    <div class="text-red-600">
                                        Sold: -{{ rtrim(rtrim(number_format($item['crypto_sold'], 8), '0'), '.') }}
                                    </div>
                                @endif
                                @if(isset($item['tracked_holdings']))
                                    <div class="text-purple-600">
                                        Tracked holdings: {{ rtrim(rtrim(number_format($item['tracked_holdings'], 8), '0'), '.') }}
                                    </div>
                                @endif
                            </div>
                            @if($item['previous_quantity'] && $item['suggested_quantity'] != $item['previous_quantity'])
                                <div class="mt-2 text-xs text-purple-600">
                                    Suggested quantity: {{ rtrim(rtrim(number_format($item['previous_quantity'], 8), '0'), '.') }}
                                    @if($item['crypto_bought'] > 0)
                                        + {{ rtrim(rtrim(number_format($item['crypto_bought'], 8), '0'), '.') }}
                                    @endif
                                    @if($item['crypto_sold'] > 0)
                                        - {{ rtrim(rtrim(number_format($item['crypto_sold'], 8), '0'), '.') }}
                                    @endif
                                    = <span class="font-medium">{{ rtrim(rtrim(number_format($item['suggested_quantity'], 8), '0'), '.') }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Editable fields for the new year --}}
                    <div class="flex items-end gap-3 flex-wrap">
                        @if(in_array($item['asset_type'], ['crypto', 'stock']))
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">{{ $taxYear->year }} Quantity</label>
                                <input type="text" name="items[{{ $index }}][quantity]"
                                    value="{{ $item['suggested_quantity'] ? rtrim(rtrim(number_format($item['suggested_quantity'], 8), '0'), '.') : '' }}"
                                    placeholder="0.00"
                                    class="border border-stone-300 rounded px-3 py-2 text-sm w-44 focus:outline-none focus:ring-2 focus:ring-stone-400 font-mono">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">Dec 31, {{ $taxYear->year }} Price ($)</label>
                                <input type="text" name="items[{{ $index }}][unit_price_year_end]"
                                    value=""
                                    placeholder="Enter new price"
                                    class="border border-stone-300 rounded px-3 py-2 text-sm w-44 focus:outline-none focus:ring-2 focus:ring-stone-400">
                            </div>
                        @else
                            <div>
                                <label class="block text-xs font-medium text-stone-500 mb-1">Dec 31, {{ $taxYear->year }} Total Value ($)</label>
                                <input type="text" name="items[{{ $index }}][total_value]"
                                    value=""
                                    placeholder="Enter new value"
                                    class="border border-stone-300 rounded px-3 py-2 text-sm w-44 focus:outline-none focus:ring-2 focus:ring-stone-400">
                            </div>
                        @endif
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-stone-500 mb-1">Notes</label>
                            <input type="text" name="items[{{ $index }}][notes]"
                                value="{{ $item['notes'] }}"
                                placeholder="Optional"
                                class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Create {{ $taxYear->year }} Balance Sheet
            </button>
            <a href="{{ url('/tax-years/' . $taxYear->year . '/balance-sheet') }}" class="text-sm text-stone-500 hover:text-stone-700">Cancel</a>
        </div>
    </form>
@endsection