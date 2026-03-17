@extends('layouts.app')

@section('title', $asset->name . ' - Crypto - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/crypto') }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to Crypto</a>
        <div class="flex items-center justify-between mt-2">
            <h1 class="text-2xl font-bold">{{ $asset->name }} <span class="text-stone-400 font-normal">{{ $asset->symbol }}</span></h1>
            <a href="{{ url('/crypto/' . $asset->id . '/import') }}" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Import CSV
            </a>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Current Holdings</div>
            <div class="text-xl font-bold font-mono">{{ rtrim(rtrim(number_format($totalHoldings, 8), '0'), '.') }} {{ $asset->symbol }}</div>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Remaining Cost Basis</div>
            <div class="text-xl font-bold">${{ number_format($totalCostBasis, 2) }}</div>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Total Proceeds (Sells)</div>
            <div class="text-xl font-bold">${{ number_format($totalProceeds, 2) }}</div>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <div class="text-sm text-stone-500">Realized Gain/Loss</div>
            <div class="text-xl font-bold {{ $totalGainLoss >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                ${{ number_format($totalGainLoss, 2) }}
            </div>
        </div>
    </div>

    {{-- Unallocated Sells Warning --}}
    @if($unallocatedSells->isNotEmpty())
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-8">
            <div class="font-medium text-amber-800">{{ $unallocatedSells->count() }} sell{{ $unallocatedSells->count() > 1 ? 's' : '' }} need buy allocation</div>
            <p class="text-sm text-amber-600 mt-1">Scroll down to the sells table and click "Allocate" to assign buys.</p>
        </div>
    @endif

    {{-- Add Buy --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-8">
        <h2 class="font-medium mb-3">Record Buy</h2>
        <form action="{{ url('/crypto/' . $asset->id . '/buys') }}" method="POST" class="flex items-end gap-3 flex-wrap">
            @csrf
            @if($errors->any())
                <div class="w-full mb-2">
                    @foreach($errors->all() as $error)
                        <div class="text-red-500 text-sm">{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            <div>
                <label class="block text-xs font-medium text-stone-500 mb-1">Date</label>
                <input type="date" name="date" required value="{{ old('date') }}" class="border border-stone-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-500 mb-1">Quantity</label>
                <input type="text" name="quantity" required placeholder="0.00000000" value="{{ old('quantity') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-stone-400 font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-500 mb-1">Cost per unit ($)</label>
                <input type="text" name="cost_per_unit" required placeholder="0.00" value="{{ old('cost_per_unit') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-500 mb-1">Fee ($)</label>
                <input type="text" name="fee" placeholder="0.00" value="{{ old('fee') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-28 focus:outline-none focus:ring-2 focus:ring-stone-400">
            </div>
            <div class="flex-1">
                <label class="block text-xs font-medium text-stone-500 mb-1">Notes (optional)</label>
                <input type="text" name="notes" placeholder="e.g. Coinbase purchase" value="{{ old('notes') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
            </div>
            <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Add Buy
            </button>
        </form>
    </div>

    {{-- Buys Table --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Buys</h2>
        @if($buys->where('quantity_remaining', '>', 0)->count() > 0)
            <a href="{{ url('/crypto/' . $asset->id . '/sell') }}" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Record Sell
            </a>
        @endif
    </div>

    @if($buys->isEmpty())
        <div class="text-stone-400 text-sm mb-8">No buys recorded yet.</div>
    @else
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-8">
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium text-right">Quantity</th>
                        <th class="px-4 py-3 font-medium text-right">Remaining</th>
                        <th class="px-4 py-3 font-medium text-right">Cost/Unit</th>
                        <th class="px-4 py-3 font-medium text-right">Fee</th>
                        <th class="px-4 py-3 font-medium text-right">Total Cost</th>
                        <th class="px-4 py-3 font-medium">Notes</th>
                        <th class="px-4 py-3 font-medium text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($buys as $buy)
                        <tr class="{{ $buy->quantity_remaining <= 0 ? 'text-stone-400' : '' }}">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $buy->date->format('m/d/Y') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ rtrim(rtrim(number_format($buy->quantity, 8), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ rtrim(rtrim(number_format($buy->quantity_remaining, 8), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($buy->cost_per_unit, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $buy->fee > 0 ? '$' . number_format($buy->fee, 2) : '' }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($buy->total_cost, 2) }}</td>
                            <td class="px-4 py-3 text-stone-500">{{ $buy->notes ?? '' }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($buy->quantity_remaining == $buy->quantity)
                                    <form action="{{ url('/crypto/buys/' . $buy->id) }}" method="POST" onsubmit="return confirm('Delete this buy?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Sells Table --}}
    <h2 class="text-lg font-bold mb-4">Sells</h2>

    @if($sells->isEmpty())
        <div class="text-stone-400 text-sm mb-8">No sells recorded yet.</div>
    @else
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-8">
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium text-right">Quantity</th>
                        <th class="px-4 py-3 font-medium text-right">Price/Unit</th>
                        <th class="px-4 py-3 font-medium text-right">Fee</th>
                        <th class="px-4 py-3 font-medium text-right">Proceeds</th>
                        <th class="px-4 py-3 font-medium text-right">Cost Basis</th>
                        <th class="px-4 py-3 font-medium text-right">Gain/Loss</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sells as $sell)
                        @php
                            $isAllocated = $sell->buys->isNotEmpty();
                            $hasLongTerm = $sell->buys->contains(fn($b) => $b->pivot->is_long_term);
                            $hasShortTerm = $sell->buys->contains(fn($b) => !$b->pivot->is_long_term);
                        @endphp
                        <tr class="border-t border-stone-100 {{ $isAllocated ? 'cursor-pointer hover:bg-stone-50' : '' }}"
                            @if($isAllocated) onclick="document.getElementById('sell-{{ $sell->id }}').classList.toggle('hidden')" @endif>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $sell->date->format('m/d/Y') }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ rtrim(rtrim(number_format($sell->quantity, 8), '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($sell->price_per_unit, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $sell->fee > 0 ? '$' . number_format($sell->fee, 2) : '' }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($sell->total_proceeds, 2) }}</td>
                            <td class="px-4 py-3 text-right">{{ $isAllocated ? '$' . number_format($sell->total_cost_basis, 2) : '—' }}</td>
                            <td class="px-4 py-3 text-right {{ $isAllocated ? ($sell->gain_loss >= 0 ? 'text-emerald-600' : 'text-red-600') : '' }}">
                                {{ $isAllocated ? '$' . number_format($sell->gain_loss, 2) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @if(!$isAllocated)
                                    <a href="{{ url('/crypto/sells/' . $sell->id . '/allocate') }}" class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded hover:bg-amber-200" onclick="event.stopPropagation()">
                                        Allocate
                                    </a>
                                @elseif($hasLongTerm && $hasShortTerm)
                                    <span class="text-xs bg-amber-50 text-amber-600 px-2 py-0.5 rounded">Mixed</span>
                                @elseif($hasLongTerm)
                                    <span class="text-xs bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded">Long</span>
                                @else
                                    <span class="text-xs bg-stone-100 text-stone-600 px-2 py-0.5 rounded">Short</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ url('/crypto/sells/' . $sell->id) }}" method="POST" onsubmit="event.stopPropagation(); return confirm('Delete this sell? Buy quantities will be restored.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @if($isAllocated)
                            <tr id="sell-{{ $sell->id }}" class="hidden">
                                <td colspan="9" class="px-0 py-0">
                                    <table class="w-full text-sm">
                                        <tbody>
                                            @foreach($sell->buys as $buy)
                                                <tr class="{{ $loop->index % 2 === 0 ? 'bg-stone-50' : 'bg-white' }}">
                                                    <td class="px-6 py-1.5 text-stone-500">Buy: {{ $buy->date->format('m/d/Y') }}</td>
                                                    <td class="px-4 py-1.5 text-right font-mono">{{ rtrim(rtrim(number_format($buy->pivot->quantity, 8), '0'), '.') }}</td>
                                                    <td class="px-4 py-1.5 text-right">${{ number_format($buy->cost_per_unit, 2) }}/unit</td>
                                                    <td class="px-4 py-1.5 text-right">Basis: ${{ number_format($buy->pivot->cost_basis, 2) }}</td>
                                                    <td class="px-4 py-1.5">
                                                        @if($buy->pivot->is_long_term)
                                                            <span class="text-xs text-emerald-600">Long-term</span>
                                                        @else
                                                            <span class="text-xs text-stone-500">Short-term</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection