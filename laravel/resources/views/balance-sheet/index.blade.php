@extends('layouts.app')

@section('title', 'Balance Sheet - ' . $taxYear->year . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/tax-years/' . $taxYear->year) }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $taxYear->year }}</a>
        <div class="flex items-center justify-between mt-2">
            <h1 class="text-2xl font-bold">Balance Sheet — December 31, {{ $taxYear->year }}</h1>
            @if($canCopyFromPrevious)
                <a href="{{ url('/tax-years/' . $taxYear->year . '/balance-sheet/copy') }}" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                    Copy from {{ $taxYear->year - 1 }}
                </a>
            @endif
        </div>
    </div>

    {{-- Total --}}
    <div class="bg-white border border-stone-200 rounded-lg p-4 mb-8">
        <div class="text-sm text-stone-500">Total Assets</div>
        <div class="text-2xl font-bold">${{ number_format($totalAssets, 2) }}</div>
    </div>

    {{-- Add Item --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-8">
        <h2 class="font-medium mb-3">Add Asset</h2>
        <form action="{{ url('/tax-years/' . $taxYear->year . '/balance-sheet') }}" method="POST">
            @csrf
            @if($errors->any())
                <div class="mb-3">
                    @foreach($errors->all() as $error)
                        <div class="text-red-500 text-sm">{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            <div class="flex items-end gap-3 flex-wrap">
                <div>
                    <label class="block text-xs font-medium text-stone-500 mb-1">Type</label>
                    <select name="asset_type" id="asset-type-select" required class="border border-stone-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
                        <option value="crypto">Crypto</option>
                        <option value="stock">Stock</option>
                        <option value="cash">Cash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div id="crypto-asset-field">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Crypto Asset</label>
                    <select name="crypto_asset_id" class="border border-stone-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
                        <option value="">Select...</option>
                        @foreach($cryptoAssets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }} ({{ $asset->symbol }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Label</label>
                    <input type="text" name="label" required placeholder="e.g. Bitcoin, Business Checking" value="{{ old('label') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                </div>
                <div id="quantity-field">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Quantity</label>
                    <input type="text" name="quantity" placeholder="0.00" value="{{ old('quantity') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400 font-mono">
                </div>
                <div id="unit-price-field">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Dec 31 Price ($)</label>
                    <input type="text" name="unit_price_year_end" placeholder="0.00" value="{{ old('unit_price_year_end') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400">
                </div>
                <div id="total-value-field" class="hidden">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Total Value ($)</label>
                    <input type="text" name="total_value" placeholder="0.00" value="{{ old('total_value') }}" class="border border-stone-300 rounded px-3 py-2 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400">
                </div>
                <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                    Add
                </button>
            </div>
        </form>
    </div>

    {{-- Items Table --}}
    @if($items->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No assets on this balance sheet yet.</p>
        </div>
    @else
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Asset</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium text-right">Quantity</th>
                        <th class="px-4 py-3 font-medium text-right">Dec 31 Price</th>
                        <th class="px-4 py-3 font-medium text-right">Total Value</th>
                        <th class="px-4 py-3 font-medium">Notes</th>
                        <th class="px-4 py-3 font-medium text-right"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr class="border-t border-stone-100 cursor-pointer hover:bg-stone-50"
                            onclick="document.getElementById('edit-{{ $item->id }}').classList.toggle('hidden')">
                            <td class="px-4 py-3 font-medium">{{ $item->label }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded
                                    {{ $item->asset_type === 'crypto' ? 'bg-purple-50 text-purple-600' : '' }}
                                    {{ $item->asset_type === 'stock' ? 'bg-blue-50 text-blue-600' : '' }}
                                    {{ $item->asset_type === 'cash' ? 'bg-emerald-50 text-emerald-600' : '' }}
                                    {{ $item->asset_type === 'other' ? 'bg-stone-100 text-stone-600' : '' }}
                                ">{{ ucfirst($item->asset_type) }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                @if($item->quantity)
                                    {{ rtrim(rtrim(number_format($item->quantity, 8), '0'), '.') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($item->unit_price_year_end)
                                    ${{ number_format($item->unit_price_year_end, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-medium">${{ number_format($item->total_value, 2) }}</td>
                            <td class="px-4 py-3 text-stone-500">{{ $item->notes ?? '' }}</td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ url('/balance-sheet/' . $item->id) }}" method="POST" onsubmit="event.stopPropagation(); return confirm('Remove this item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>

                        {{-- Crypto hints --}}
                        @if(isset($cryptoHints[$item->id]))
                            <tr class="bg-purple-50">
                                <td colspan="7" class="px-6 py-2 text-xs text-purple-600">
                                    Tracked in crypto module:
                                    Current holdings: {{ rtrim(rtrim(number_format($cryptoHints[$item->id]['current_holdings'], 8), '0'), '.') }}
                                    @if($cryptoHints[$item->id]['bought'] > 0)
                                        · Bought in {{ $taxYear->year }}: +{{ rtrim(rtrim(number_format($cryptoHints[$item->id]['bought'], 8), '0'), '.') }}
                                    @endif
                                    @if($cryptoHints[$item->id]['sold'] > 0)
                                        · Sold in {{ $taxYear->year }}: -{{ rtrim(rtrim(number_format($cryptoHints[$item->id]['sold'], 8), '0'), '.') }}
                                    @endif
                                </td>
                            </tr>
                        @endif

                        {{-- Inline edit row --}}
                        <tr id="edit-{{ $item->id }}" class="hidden bg-stone-50">
                            <td colspan="7" class="px-4 py-3">
                                <form action="{{ url('/balance-sheet/' . $item->id) }}" method="POST" class="flex items-end gap-3 flex-wrap">
                                    @csrf
                                    @method('PATCH')
                                    <div>
                                        <label class="block text-xs font-medium text-stone-500 mb-1">Quantity</label>
                                        <input type="text" name="quantity" value="{{ $item->quantity ? rtrim(rtrim(number_format($item->quantity, 8), '0'), '.') : '' }}" placeholder="0.00" class="border border-stone-300 rounded px-3 py-1.5 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400 font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-stone-500 mb-1">Dec 31 Price ($)</label>
                                        <input type="text" name="unit_price_year_end" value="{{ $item->unit_price_year_end ? number_format($item->unit_price_year_end, 2) : '' }}" placeholder="0.00" class="border border-stone-300 rounded px-3 py-1.5 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-stone-500 mb-1">Total Value ($)</label>
                                        <input type="text" name="total_value" value="{{ number_format($item->total_value, 2) }}" placeholder="0.00" class="border border-stone-300 rounded px-3 py-1.5 text-sm w-36 focus:outline-none focus:ring-2 focus:ring-stone-400">
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-stone-500 mb-1">Notes</label>
                                        <input type="text" name="notes" value="{{ $item->notes }}" placeholder="Optional" class="border border-stone-300 rounded px-3 py-1.5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                                    </div>
                                    <button type="submit" class="bg-stone-800 text-white px-4 py-1.5 rounded text-sm hover:bg-stone-700 transition-colors">
                                        Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <script>
        // Toggle form fields based on asset type
        var typeSelect = document.getElementById('asset-type-select');
        var cryptoField = document.getElementById('crypto-asset-field');
        var quantityField = document.getElementById('quantity-field');
        var unitPriceField = document.getElementById('unit-price-field');
        var totalValueField = document.getElementById('total-value-field');

        function toggleFields() {
            var type = typeSelect.value;

            if (type === 'crypto') {
                cryptoField.classList.remove('hidden');
                quantityField.classList.remove('hidden');
                unitPriceField.classList.remove('hidden');
                totalValueField.classList.add('hidden');
            } else if (type === 'stock') {
                cryptoField.classList.add('hidden');
                quantityField.classList.remove('hidden');
                unitPriceField.classList.remove('hidden');
                totalValueField.classList.add('hidden');
            } else {
                cryptoField.classList.add('hidden');
                quantityField.classList.add('hidden');
                unitPriceField.classList.add('hidden');
                totalValueField.classList.remove('hidden');
            }
        }

        typeSelect.addEventListener('change', toggleFields);
        toggleFields();
    </script>
@endsection