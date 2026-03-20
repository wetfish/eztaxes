@extends('layouts.app')

@section('title', 'Crypto - EzTaxes')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <h1 class="text-2xl font-bold">Crypto Assets</h1>
    </div>

    {{-- Add Asset --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-8">
        <h2 class="font-medium mb-3">Add Asset</h2>
        <form action="{{ url('/crypto') }}" method="POST" class="grid grid-cols-1 sm:flex sm:items-end gap-3">
            @csrf
            <div class="sm:flex-1">
                <label class="block text-xs font-medium text-stone-500 mb-1">Name</label>
                <input type="text" name="name" required placeholder="e.g. Bitcoin" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
            </div>
            <div class="sm:w-32">
                <label class="block text-xs font-medium text-stone-500 mb-1">Symbol</label>
                <input type="text" name="symbol" required placeholder="e.g. BTC" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400 uppercase">
            </div>
            <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors w-full sm:w-auto">
                Add
            </button>
        </form>
    </div>

    @if($assets->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No crypto assets yet.</p>
            <p class="text-sm mt-2">Add one above to start tracking buys and sells.</p>
        </div>
    @else
        <div class="bg-white border border-stone-200 rounded-lg overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Asset</th>
                        <th class="px-4 py-3 font-medium text-right">Balance Sheet Holdings</th>
                        <th class="px-4 py-3 font-medium text-right">Tracked Holdings</th>
                        <th class="px-4 py-3 font-medium text-right">Buys</th>
                        <th class="px-4 py-3 font-medium text-right">Sells</th>
                        <th class="px-4 py-3 font-medium text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($assets as $asset)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ url('/crypto/' . $asset->id) }}" class="font-medium hover:underline">{{ $asset->name }}</a>
                                <span class="text-stone-400 ml-1">{{ $asset->symbol }}</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                @if($asset->balance_sheet_quantity !== null)
                                    {{ rtrim(rtrim(number_format($asset->balance_sheet_quantity, 8), '0'), '.') }}
                                    <span class="text-xs text-stone-400 font-sans ml-1">({{ $asset->balance_sheet_year }})</span>
                                @else
                                    <span class="text-stone-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono">
                                {{ rtrim(rtrim(number_format($asset->total_holdings, 8), '0'), '.') }}
                                @if($asset->has_discrepancy)
                                    <span class="inline-block ml-1 text-amber-500" title="Tracked holdings don't match balance sheet">⚠</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">{{ $asset->buys_count }}</td>
                            <td class="px-4 py-3 text-right">{{ $asset->sells_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ url('/crypto/' . $asset->id) }}" method="POST" onsubmit="return confirm('Delete this asset and all its buys/sells?')">
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