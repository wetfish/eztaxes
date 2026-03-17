<?php

namespace App\Http\Controllers;

use App\Models\BalanceSheetItem;
use App\Models\CryptoAsset;
use App\Models\CryptoBuy;
use App\Models\CryptoSell;
use App\Models\TaxYear;
use Illuminate\Http\Request;

class BalanceSheetController extends Controller
{
    public function index(int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $items = BalanceSheetItem::where('tax_year_id', $taxYear->id)
            ->orderBy('sort_order')
            ->get();

        $cryptoAssets = CryptoAsset::orderBy('name')->get();

        // Build crypto activity hints for linked items
        $cryptoHints = [];

        foreach ($items->where('crypto_asset_id', '!=', null) as $item) {
            $assetId = $item->crypto_asset_id;

            $buyQuantity = CryptoBuy::where('crypto_asset_id', $assetId)
                ->whereYear('date', $year)
                ->sum('quantity');

            $sellQuantity = CryptoSell::where('crypto_asset_id', $assetId)
                ->whereHas('buys')
                ->whereYear('date', $year)
                ->sum('quantity');

            $currentHoldings = CryptoBuy::where('crypto_asset_id', $assetId)
                ->sum('quantity_remaining');

            if ($buyQuantity > 0 || $sellQuantity > 0 || $currentHoldings > 0) {
                $cryptoHints[$item->id] = [
                    'bought' => $buyQuantity,
                    'sold' => $sellQuantity,
                    'current_holdings' => $currentHoldings,
                ];
            }
        }

        $totalAssets = $items->sum('total_value');

        // Check if previous year has a balance sheet to copy from
        $previousYear = TaxYear::where('year', $year - 1)->first();
        $canCopyFromPrevious = $previousYear &&
            BalanceSheetItem::where('tax_year_id', $previousYear->id)->exists();

        return view('balance-sheet.index', compact('taxYear', 'items', 'cryptoAssets', 'cryptoHints', 'totalAssets', 'canCopyFromPrevious'));
    }

    public function store(Request $request, int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $request->merge([
            'quantity' => $this->cleanNumeric($request->quantity),
            'unit_price_year_end' => $this->cleanNumeric($request->unit_price_year_end),
            'total_value' => $this->cleanNumeric($request->total_value),
        ]);

        $request->validate([
            'label' => 'required|string|max:255',
            'asset_type' => 'required|in:crypto,stock,cash,other',
            'crypto_asset_id' => 'nullable|exists:crypto_assets,id',
            'quantity' => 'nullable|numeric|min:0',
            'unit_price_year_end' => 'nullable|numeric|min:0',
            'total_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // Calculate total_value from quantity * price if both provided
        $quantity = $request->quantity;
        $unitPrice = $request->unit_price_year_end;
        $totalValue = $request->total_value ?? 0;

        if ($quantity && $unitPrice) {
            $totalValue = round($quantity * $unitPrice, 2);
        }

        $maxSort = BalanceSheetItem::where('tax_year_id', $taxYear->id)->max('sort_order') ?? 0;

        BalanceSheetItem::create([
            'tax_year_id' => $taxYear->id,
            'crypto_asset_id' => $request->asset_type === 'crypto' ? $request->crypto_asset_id : null,
            'label' => $request->label,
            'asset_type' => $request->asset_type,
            'quantity' => $quantity,
            'unit_price_year_end' => $unitPrice,
            'total_value' => $totalValue,
            'notes' => $request->notes,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect("/tax-years/{$year}/balance-sheet")->with('success', "Added '{$request->label}' to balance sheet.");
    }

    public function update(Request $request, int $id)
    {
        $item = BalanceSheetItem::findOrFail($id);

        $request->merge([
            'quantity' => $this->cleanNumeric($request->quantity),
            'unit_price_year_end' => $this->cleanNumeric($request->unit_price_year_end),
            'total_value' => $this->cleanNumeric($request->total_value),
        ]);

        $request->validate([
            'quantity' => 'nullable|numeric|min:0',
            'unit_price_year_end' => 'nullable|numeric|min:0',
            'total_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $quantity = $request->quantity;
        $unitPrice = $request->unit_price_year_end;
        $totalValue = $request->total_value ?? $item->total_value;

        if ($quantity && $unitPrice) {
            $totalValue = round($quantity * $unitPrice, 2);
        }

        $item->update([
            'quantity' => $quantity,
            'unit_price_year_end' => $unitPrice,
            'total_value' => $totalValue,
            'notes' => $request->notes,
        ]);

        $year = $item->taxYear->year;

        return redirect("/tax-years/{$year}/balance-sheet")->with('success', "Updated '{$item->label}'.");
    }

    public function destroy(int $id)
    {
        $item = BalanceSheetItem::findOrFail($id);
        $year = $item->taxYear->year;
        $label = $item->label;

        $item->delete();

        return redirect("/tax-years/{$year}/balance-sheet")->with('success', "Removed '{$label}' from balance sheet.");
    }

    public function copyPreview(int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();
        $previousYear = TaxYear::where('year', $year - 1)->first();

        if (!$previousYear) {
            return redirect("/tax-years/{$year}/balance-sheet")
                ->with('error', "No balance sheet found for " . ($year - 1) . ". Create that year's balance sheet first.");
        }

        $previousItems = BalanceSheetItem::where('tax_year_id', $previousYear->id)
            ->orderBy('sort_order')
            ->get();

        if ($previousItems->isEmpty()) {
            return redirect("/tax-years/{$year}/balance-sheet")
                ->with('error', "The " . ($year - 1) . " balance sheet has no items to copy.");
        }

        // Build adjusted items with crypto activity
        $adjustedItems = [];

        foreach ($previousItems as $item) {
            $adjusted = [
                'label' => $item->label,
                'asset_type' => $item->asset_type,
                'crypto_asset_id' => $item->crypto_asset_id,
                'previous_quantity' => $item->quantity,
                'previous_unit_price' => $item->unit_price_year_end,
                'previous_total_value' => $item->total_value,
                'suggested_quantity' => $item->quantity,
                'unit_price_year_end' => null,
                'notes' => $item->notes,
                'crypto_bought' => 0,
                'crypto_sold' => 0,
            ];

            // For crypto items, calculate adjustments from tracked activity
            if ($item->crypto_asset_id) {
                $assetId = $item->crypto_asset_id;

                $bought = CryptoBuy::where('crypto_asset_id', $assetId)
                    ->whereYear('date', $year)
                    ->sum('quantity');

                $sold = CryptoSell::where('crypto_asset_id', $assetId)
                    ->whereHas('buys')
                    ->whereYear('date', $year)
                    ->sum('quantity');

                $adjusted['crypto_bought'] = $bought;
                $adjusted['crypto_sold'] = $sold;

                if ($item->quantity) {
                    $adjusted['suggested_quantity'] = $item->quantity + $bought - $sold;
                }

                // Also check current tracked holdings for reference
                $adjusted['tracked_holdings'] = CryptoBuy::where('crypto_asset_id', $assetId)
                    ->sum('quantity_remaining');
            }

            $adjustedItems[] = $adjusted;
        }

        return view('balance-sheet.copy', compact('taxYear', 'previousYear', 'adjustedItems'));
    }

    public function copyProcess(Request $request, int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $items = $request->input('items', []);

        if (empty($items)) {
            return redirect("/tax-years/{$year}/balance-sheet")->with('error', 'No items to copy.');
        }

        $created = 0;

        foreach ($items as $index => $itemData) {
            if (empty($itemData['include'])) {
                continue;
            }

            $quantity = $this->cleanNumeric($itemData['quantity'] ?? null);
            $unitPrice = $this->cleanNumeric($itemData['unit_price_year_end'] ?? null);
            $totalValue = $this->cleanNumeric($itemData['total_value'] ?? null);

            if ($quantity && $unitPrice) {
                $totalValue = round((float) $quantity * (float) $unitPrice, 2);
            }

            BalanceSheetItem::create([
                'tax_year_id' => $taxYear->id,
                'crypto_asset_id' => $itemData['crypto_asset_id'] ?: null,
                'label' => $itemData['label'],
                'asset_type' => $itemData['asset_type'],
                'quantity' => $quantity,
                'unit_price_year_end' => $unitPrice,
                'total_value' => $totalValue ?? 0,
                'notes' => $itemData['notes'] ?? null,
                'sort_order' => $created,
            ]);

            $created++;
        }

        return redirect("/tax-years/{$year}/balance-sheet")
            ->with('success', "Copied {$created} items from " . ($year - 1) . ". Update Dec 31 prices to finalize.");
    }

    private function cleanNumeric(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return preg_replace('/[^0-9.\-]/', '', $value);
    }
}