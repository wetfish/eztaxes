@extends('layouts.app')

@section('title', 'Allocate Sell - ' . $asset->symbol . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/crypto/' . $asset->id) }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $asset->name }}</a>
        <h1 class="text-2xl font-bold mt-2">Allocate Sell — {{ $asset->name }} <span class="text-stone-400 font-normal">{{ $asset->symbol }}</span></h1>
    </div>

    {{-- Sell Details --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-6">
        <h2 class="font-medium mb-3">Sell Transaction</h2>
        <div class="grid grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs text-stone-500">Date</div>
                <div class="font-medium">{{ $sell->date->format('m/d/Y') }}</div>
            </div>
            <div>
                <div class="text-xs text-stone-500">Quantity</div>
                <div class="font-medium font-mono">{{ rtrim(rtrim(number_format($sell->quantity, 8), '0'), '.') }} {{ $asset->symbol }}</div>
            </div>
            <div>
                <div class="text-xs text-stone-500">Price/Unit</div>
                <div class="font-medium">${{ number_format($sell->price_per_unit, 2) }}</div>
            </div>
            <div>
                <div class="text-xs text-stone-500">Total Proceeds (after fees)</div>
                <div class="font-medium">${{ number_format($sell->total_proceeds, 2) }}{{ $sell->fee > 0 ? ' (fee: $' . number_format($sell->fee, 2) . ')' : '' }}</div>
            </div>
        </div>
    </div>

    <form action="{{ url('/crypto/sells/' . $sell->id . '/allocate') }}" method="POST">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                @foreach($errors->all() as $error)
                    <div class="text-red-600 text-sm">{{ $error }}</div>
                @endforeach
            </div>
        @endif

        {{-- Buy Allocations --}}
        <div class="bg-white border border-stone-200 rounded-lg p-5 mb-6">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-medium">Allocate from Buys</h2>
                <div class="text-sm text-stone-500">
                    Need: <span class="font-mono font-medium text-stone-800">{{ rtrim(rtrim(number_format($sell->quantity, 8), '0'), '.') }}</span>
                    &mdash;
                    Allocated: <span id="total-allocated" class="font-mono font-medium text-stone-800">0</span>
                </div>
            </div>
            <p class="text-xs text-stone-400 mb-4">Enter how much to draw from each buy. The total must equal the sell quantity.</p>

            @if($availableBuys->isEmpty())
                <div class="text-stone-400 text-sm">No buys with remaining quantity available.</div>
            @else
                <div class="bg-white border border-stone-200 rounded-lg overflow-hidden">
                    <div class="max-h-64 overflow-y-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-stone-100 text-left sticky top-0 z-10">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Buy Date</th>
                                    <th class="px-4 py-3 font-medium text-right">Cost/Unit</th>
                                    <th class="px-4 py-3 font-medium text-right">Original Qty</th>
                                    <th class="px-4 py-3 font-medium text-right">Remaining</th>
                                    <th class="px-4 py-3 font-medium">Notes</th>
                                    <th class="px-4 py-3 font-medium text-right">Allocate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-stone-100">
                                @foreach($availableBuys as $index => $buy)
                                    <tr>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $buy->date->format('m/d/Y') }}</td>
                                        <td class="px-4 py-3 text-right">${{ number_format($buy->cost_per_unit, 2) }}</td>
                                        <td class="px-4 py-3 text-right font-mono">{{ rtrim(rtrim(number_format($buy->quantity, 8), '0'), '.') }}</td>
                                        <td class="px-4 py-3 text-right font-mono">{{ rtrim(rtrim(number_format($buy->quantity_remaining, 8), '0'), '.') }}</td>
                                        <td class="px-4 py-3 text-stone-500">{{ $buy->notes ?? '' }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <input type="hidden" name="buy_allocations[{{ $index }}][buy_id]" value="{{ $buy->id }}">
                                            <div class="flex items-center justify-end gap-1">
                                                <input
                                                    type="text"
                                                    name="buy_allocations[{{ $index }}][quantity]"
                                                    value="{{ old("buy_allocations.{$index}.quantity", '0') }}"
                                                    placeholder="0"
                                                    data-max="{{ $buy->quantity_remaining }}"
                                                    class="allocation-input border border-stone-300 rounded px-2 py-1 text-sm w-36 text-right focus:outline-none focus:ring-2 focus:ring-stone-400 font-mono"
                                                >
                                                <button type="button" onclick="fillMax(this)" class="text-xs bg-stone-100 hover:bg-stone-200 text-stone-600 px-2 py-1 rounded whitespace-nowrap">Max</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Save Allocation
            </button>
            <a href="{{ url('/crypto/' . $asset->id) }}" class="text-sm text-stone-500 hover:text-stone-700">Cancel</a>
        </div>
    </form>

    <script>
        var sellQuantity = {{ $sell->quantity }};

        document.querySelectorAll('.allocation-input').forEach(function(input) {
            input.addEventListener('input', function() {
                enforceLimit(this);
                updateTotal();
            });
        });

        function getOtherTotal(excludeInput) {
            var total = 0;
            document.querySelectorAll('.allocation-input').forEach(function(input) {
                if (input !== excludeInput) {
                    var val = parseFloat(input.value);
                    if (!isNaN(val) && val > 0) total += val;
                }
            });
            return total;
        }

        function enforceLimit(input) {
            var val = parseFloat(input.value);
            if (isNaN(val) || val < 0) { input.value = 0; return; }

            var maxFromBuy = parseFloat(input.dataset.max);
            var otherTotal = getOtherTotal(input);
            var stillNeeded = Math.max(0, sellQuantity - otherTotal);
            var limit = Math.min(maxFromBuy, stillNeeded);

            if (val > limit) input.value = parseFloat(limit.toFixed(8));
        }

        function fillMax(button) {
            var input = button.parentElement.querySelector('.allocation-input');
            var maxFromBuy = parseFloat(input.dataset.max);
            var otherTotal = getOtherTotal(input);
            var stillNeeded = Math.max(0, sellQuantity - otherTotal);
            input.value = Math.min(maxFromBuy, stillNeeded).toFixed(8).replace(/\.?0+$/, '');
            updateTotal();
        }

        function updateTotal() {
            var total = 0;
            document.querySelectorAll('.allocation-input').forEach(function(input) {
                var val = parseFloat(input.value);
                if (!isNaN(val) && val > 0) total += val;
            });
            document.getElementById('total-allocated').textContent = total.toFixed(8).replace(/\.?0+$/, '');
        }

        updateTotal();
    </script>
@endsection