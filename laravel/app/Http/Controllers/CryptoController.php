<?php

namespace App\Http\Controllers;

use App\Models\CryptoAsset;
use App\Models\CryptoBuy;
use App\Models\CryptoSell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CryptoController extends Controller
{
    public function index()
    {
        $assets = CryptoAsset::withCount(['buys', 'sells'])->orderBy('name')->get();

        // Calculate total holdings for each asset
        foreach ($assets as $asset) {
            $asset->total_holdings = CryptoBuy::where('crypto_asset_id', $asset->id)
                ->sum('quantity_remaining');
        }

        return view('crypto.index', compact('assets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:20|unique:crypto_assets,symbol',
        ]);

        CryptoAsset::create([
            'name' => $request->name,
            'symbol' => strtoupper(trim($request->symbol)),
        ]);

        return redirect('/crypto')->with('success', "Asset '{$request->name}' created.");
    }

    public function show(int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        $buys = CryptoBuy::where('crypto_asset_id', $asset->id)
            ->orderBy('date')
            ->get();

        $sells = CryptoSell::where('crypto_asset_id', $asset->id)
            ->with('buys')
            ->orderBy('date')
            ->get();

        $totalHoldings = $buys->sum('quantity_remaining');
        $totalCostBasis = $buys->sum(function ($buy) {
            return $buy->quantity_remaining * $buy->cost_per_unit;
        });

        $totalProceeds = $sells->sum('total_proceeds');
        $totalGainLoss = $sells->sum('gain_loss');

        return view('crypto.show', compact(
            'asset', 'buys', 'sells',
            'totalHoldings', 'totalCostBasis',
            'totalProceeds', 'totalGainLoss'
        ));
    }

    public function destroy(int $id)
    {
        $asset = CryptoAsset::findOrFail($id);
        $name = $asset->name;

        $asset->delete();

        return redirect('/crypto')->with('success', "Asset '{$name}' deleted.");
    }

    public function storeBuy(Request $request, int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        // Clean numeric inputs — strip currency symbols, commas, whitespace
        $request->merge([
            'quantity' => $this->cleanNumeric($request->quantity),
            'cost_per_unit' => $this->cleanNumeric($request->cost_per_unit),
        ]);

        $request->validate([
            'date' => 'required|date',
            'quantity' => 'required|numeric|min:0.00000001',
            'cost_per_unit' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $quantity = $request->quantity;
        $costPerUnit = $request->cost_per_unit;
        $totalCost = round($quantity * $costPerUnit, 2);

        CryptoBuy::create([
            'crypto_asset_id' => $asset->id,
            'date' => $request->date,
            'quantity' => $quantity,
            'cost_per_unit' => $costPerUnit,
            'total_cost' => $totalCost,
            'quantity_remaining' => $quantity,
            'notes' => $request->notes,
        ]);

        return redirect("/crypto/{$asset->id}")->with('success', "Buy of {$quantity} {$asset->symbol} recorded.");
    }

    public function deleteBuy(int $id)
    {
        $buy = CryptoBuy::findOrFail($id);
        $assetId = $buy->crypto_asset_id;

        // Don't allow deleting a buy that has been partially or fully sold
        if ($buy->quantity_remaining < $buy->quantity) {
            return back()->with('error', 'Cannot delete a buy that has been referenced by sells. Delete the sells first.');
        }

        $buy->delete();

        return redirect("/crypto/{$assetId}")->with('success', 'Buy deleted.');
    }

    /**
     * Show the sell form with available buys to allocate from.
     */
    public function createSell(int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        $availableBuys = CryptoBuy::where('crypto_asset_id', $asset->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('date')
            ->get();

        return view('crypto.sell', compact('asset', 'availableBuys'));
    }

    public function storeSell(Request $request, int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        // Clean numeric inputs
        $request->merge([
            'quantity' => $this->cleanNumeric($request->quantity),
            'price_per_unit' => $this->cleanNumeric($request->price_per_unit),
        ]);

        // Clean allocation quantities
        if ($request->has('buy_allocations')) {
            $allocations = $request->buy_allocations;

            foreach ($allocations as $key => $allocation) {
                $allocations[$key]['quantity'] = $this->cleanNumeric($allocation['quantity'] ?? '0');
            }

            $request->merge(['buy_allocations' => $allocations]);
        }

        $request->validate([
            'date' => 'required|date',
            'quantity' => 'required|numeric|min:0.00000001',
            'price_per_unit' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'buy_allocations' => 'required|array|min:1',
            'buy_allocations.*.buy_id' => 'required|exists:crypto_buys,id',
            'buy_allocations.*.quantity' => 'required|numeric|min:0',
        ]);

        $sellDate = Carbon::parse($request->date);
        $sellQuantity = $request->quantity;
        $pricePerUnit = $request->price_per_unit;
        $totalProceeds = round($sellQuantity * $pricePerUnit, 2);

        // Filter out zero-quantity allocations and validate
        $allocations = collect($request->buy_allocations)
            ->filter(fn($a) => $a['quantity'] > 0);

        if ($allocations->isEmpty()) {
            return back()->with('error', 'You must allocate quantity from at least one buy.')->withInput();
        }

        $totalAllocated = $allocations->sum('quantity');

        if (abs($totalAllocated - $sellQuantity) > 0.00000001) {
            return back()->with('error', "Allocated quantity ({$totalAllocated}) does not match sell quantity ({$sellQuantity}).")->withInput();
        }

        // Validate each allocation doesn't exceed remaining
        foreach ($allocations as $allocation) {
            $buy = CryptoBuy::findOrFail($allocation['buy_id']);

            if ($allocation['quantity'] > $buy->quantity_remaining) {
                return back()->with('error', "Allocation of {$allocation['quantity']} from buy on {$buy->date->format('m/d/Y')} exceeds remaining quantity of {$buy->quantity_remaining}.")->withInput();
            }
        }

        // Create the sell
        $sell = CryptoSell::create([
            'crypto_asset_id' => $asset->id,
            'date' => $request->date,
            'quantity' => $sellQuantity,
            'price_per_unit' => $pricePerUnit,
            'total_proceeds' => $totalProceeds,
            'notes' => $request->notes,
        ]);

        // Create allocations and update buy remaining quantities
        $totalCostBasis = 0;

        foreach ($allocations as $allocation) {
            $buy = CryptoBuy::findOrFail($allocation['buy_id']);
            $allocationQty = $allocation['quantity'];
            $costBasis = round($allocationQty * $buy->cost_per_unit, 2);
            $isLongTerm = $buy->date->diffInDays($sellDate) > 365;

            $sell->buys()->attach($buy->id, [
                'quantity' => $allocationQty,
                'cost_basis' => $costBasis,
                'is_long_term' => $isLongTerm,
            ]);

            // Reduce the buy's remaining quantity
            $buy->update([
                'quantity_remaining' => $buy->quantity_remaining - $allocationQty,
            ]);

            $totalCostBasis += $costBasis;
        }

        // Update the sell with calculated totals
        $sell->update([
            'total_cost_basis' => $totalCostBasis,
            'gain_loss' => $totalProceeds - $totalCostBasis,
        ]);

        return redirect("/crypto/{$asset->id}")->with('success', "Sell of {$sellQuantity} {$asset->symbol} recorded. Gain/Loss: \${$sell->gain_loss}");
    }

    public function deleteSell(int $id)
    {
        $sell = CryptoSell::with('buys')->findOrFail($id);
        $assetId = $sell->crypto_asset_id;

        // Restore buy remaining quantities
        foreach ($sell->buys as $buy) {
            $buy->update([
                'quantity_remaining' => $buy->quantity_remaining + $buy->pivot->quantity,
            ]);
        }

        $sell->delete();

        return redirect("/crypto/{$assetId}")->with('success', 'Sell deleted and buy quantities restored.');
    }

    /**
     * Strip currency symbols, commas, and whitespace from a numeric input.
     */
    private function cleanNumeric(?string $value): string
    {
        if ($value === null) {
            return '0';
        }

        return preg_replace('/[^0-9.\-]/', '', $value);
    }
}