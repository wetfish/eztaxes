<?php

namespace App\Http\Controllers;

use App\Models\CryptoAsset;
use App\Models\CryptoBuy;
use App\Models\CryptoSell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class CryptoController extends Controller
{
    // ─── Assets ───

    public function index()
    {
        $assets = CryptoAsset::withCount(['buys', 'sells'])->orderBy('name')->get();

        foreach ($assets as $asset) {
            $asset->total_holdings = CryptoBuy::where('crypto_asset_id', $asset->id)
                ->sum('quantity_remaining');

            // Find the most recent balance sheet entry for this asset
            $bsItem = \App\Models\BalanceSheetItem::where('crypto_asset_id', $asset->id)
                ->join('tax_years', 'tax_years.id', '=', 'balance_sheet_items.tax_year_id')
                ->orderByDesc('tax_years.year')
                ->select('balance_sheet_items.*')
                ->first();

            $asset->balance_sheet_quantity = $bsItem?->quantity;
            $asset->balance_sheet_year = $bsItem ? \App\Models\TaxYear::find($bsItem->tax_year_id)->year : null;
            $asset->has_discrepancy = $asset->balance_sheet_quantity !== null
                && abs((float) $asset->balance_sheet_quantity - (float) $asset->total_holdings) > 0.00000001;
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
            ->get()
            ->sortBy(function ($sell) {
                // Unallocated sells first, then by date
                return ($sell->buys->isEmpty() ? '0' : '1') . $sell->date->format('Y-m-d');
            });

        $totalHoldings = $buys->sum('quantity_remaining');
        $totalCostBasis = $buys->sum(function ($buy) {
            return $buy->quantity_remaining * $buy->cost_per_unit;
        });

        $totalProceeds = $sells->sum('total_proceeds');
        $totalGainLoss = $sells->sum('gain_loss');

        $unallocatedSells = $sells->filter(fn($s) => $s->buys->isEmpty());

        // Balance sheet cross-reference
        $bsItem = \App\Models\BalanceSheetItem::where('crypto_asset_id', $asset->id)
            ->join('tax_years', 'tax_years.id', '=', 'balance_sheet_items.tax_year_id')
            ->orderByDesc('tax_years.year')
            ->select('balance_sheet_items.*')
            ->first();

        $balanceSheetQuantity = $bsItem?->quantity;
        $balanceSheetYear = $bsItem ? $bsItem->taxYear->year : null;
        $hasDiscrepancy = $balanceSheetQuantity !== null
            && abs((float) $balanceSheetQuantity - (float) $totalHoldings) > 0.00000001;

        // Tax year summaries for IRS form fields
        $allocatedSells = $sells->filter(fn($s) => $s->buys->isNotEmpty());
        $taxYearSummaries = $allocatedSells->groupBy(function ($sell) {
            return $sell->date->format('Y');
        })->map(function ($yearSells, $year) {
            $totalProceeds = $yearSells->sum('total_proceeds');
            $totalCostBasis = $yearSells->sum('total_cost_basis');
            $totalGainLoss = $yearSells->sum('gain_loss');

            // Split by long-term vs short-term
            $longTermProceeds = 0;
            $longTermCostBasis = 0;
            $shortTermProceeds = 0;
            $shortTermCostBasis = 0;

            foreach ($yearSells as $sell) {
                foreach ($sell->buys as $buy) {
                    if ($buy->pivot->is_long_term) {
                        $longTermProceeds += ($buy->pivot->quantity / $sell->quantity) * $sell->total_proceeds;
                        $longTermCostBasis += $buy->pivot->cost_basis;
                    } else {
                        $shortTermProceeds += ($buy->pivot->quantity / $sell->quantity) * $sell->total_proceeds;
                        $shortTermCostBasis += $buy->pivot->cost_basis;
                    }
                }
            }

            return [
                'year' => $year,
                'sell_count' => $yearSells->count(),
                'total_proceeds' => $totalProceeds,
                'total_cost_basis' => $totalCostBasis,
                'total_gain_loss' => $totalGainLoss,
                'long_term_proceeds' => round($longTermProceeds, 2),
                'long_term_cost_basis' => round($longTermCostBasis, 2),
                'long_term_gain_loss' => round($longTermProceeds - $longTermCostBasis, 2),
                'short_term_proceeds' => round($shortTermProceeds, 2),
                'short_term_cost_basis' => round($shortTermCostBasis, 2),
                'short_term_gain_loss' => round($shortTermProceeds - $shortTermCostBasis, 2),
            ];
        })->sortKeysDesc();

        return view('crypto.show', compact(
            'asset', 'buys', 'sells',
            'totalHoldings', 'totalCostBasis',
            'totalProceeds', 'totalGainLoss',
            'unallocatedSells', 'taxYearSummaries',
            'balanceSheetQuantity', 'balanceSheetYear', 'hasDiscrepancy'
        ));
    }

    public function destroy(int $id)
    {
        $asset = CryptoAsset::findOrFail($id);
        $name = $asset->name;

        $asset->delete();

        return redirect('/crypto')->with('success', "Asset '{$name}' deleted.");
    }

    // ─── Buys ───

    public function storeBuy(Request $request, int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        $request->merge([
            'quantity' => $this->cleanNumeric($request->quantity),
            'cost_per_unit' => $this->cleanNumeric($request->cost_per_unit),
            'fee' => $this->cleanNumeric($request->fee),
        ]);

        $request->validate([
            'date' => 'required|date',
            'quantity' => 'required|numeric|min:0.00000001',
            'cost_per_unit' => 'required|numeric|min:0',
            'fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $quantity = $request->quantity;
        $costPerUnit = $request->cost_per_unit;
        $fee = $request->fee ?? 0;
        $totalCost = round(($quantity * $costPerUnit) + $fee, 2);

        CryptoBuy::create([
            'crypto_asset_id' => $asset->id,
            'date' => $request->date,
            'quantity' => $quantity,
            'cost_per_unit' => $costPerUnit,
            'total_cost' => $totalCost,
            'fee' => $fee,
            'quantity_remaining' => $quantity,
            'notes' => $request->notes,
        ]);

        return redirect("/crypto/{$asset->id}")->with('success', "Buy of {$quantity} {$asset->symbol} recorded.");
    }

    public function deleteBuy(int $id)
    {
        $buy = CryptoBuy::findOrFail($id);
        $assetId = $buy->crypto_asset_id;

        if ($buy->quantity_remaining < $buy->quantity) {
            return back()->with('error', 'Cannot delete a buy that has been referenced by sells. Delete the sells first.');
        }

        $buy->delete();

        return redirect("/crypto/{$assetId}")->with('success', 'Buy deleted.');
    }

    // ─── Sells ───

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

        $request->merge([
            'quantity' => $this->cleanNumeric($request->quantity),
            'price_per_unit' => $this->cleanNumeric($request->price_per_unit),
            'fee' => $this->cleanNumeric($request->fee),
        ]);

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
            'fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'buy_allocations' => 'required|array|min:1',
            'buy_allocations.*.buy_id' => 'required|exists:crypto_buys,id',
            'buy_allocations.*.quantity' => 'required|numeric|min:0',
        ]);

        $sellDate = Carbon::parse($request->date);
        $sellQuantity = $request->quantity;
        $pricePerUnit = $request->price_per_unit;
        $fee = $request->fee ?? 0;
        $totalProceeds = round(($sellQuantity * $pricePerUnit) - $fee, 2);

        $allocations = collect($request->buy_allocations)
            ->filter(fn($a) => $a['quantity'] > 0);

        if ($allocations->isEmpty()) {
            return back()->with('error', 'You must allocate quantity from at least one buy.')->withInput();
        }

        $totalAllocated = $allocations->sum('quantity');

        if (abs($totalAllocated - $sellQuantity) > 0.00000001) {
            return back()->with('error', "Allocated quantity ({$totalAllocated}) does not match sell quantity ({$sellQuantity}).")->withInput();
        }

        foreach ($allocations as $allocation) {
            $buy = CryptoBuy::findOrFail($allocation['buy_id']);

            if ($allocation['quantity'] > $buy->quantity_remaining) {
                return back()->with('error', "Allocation of {$allocation['quantity']} from buy on {$buy->date->format('m/d/Y')} exceeds remaining quantity of {$buy->quantity_remaining}.")->withInput();
            }
        }

        $sell = CryptoSell::create([
            'crypto_asset_id' => $asset->id,
            'date' => $request->date,
            'quantity' => $sellQuantity,
            'price_per_unit' => $pricePerUnit,
            'total_proceeds' => $totalProceeds,
            'fee' => $fee,
            'notes' => $request->notes,
        ]);

        $this->processAllocations($sell, $allocations, $sellDate);

        return redirect("/crypto/{$asset->id}")->with('success', "Sell of {$sellQuantity} {$asset->symbol} recorded. Gain/Loss: \${$sell->gain_loss}");
    }

    public function deleteSell(int $id)
    {
        $sell = CryptoSell::with('buys')->findOrFail($id);
        $assetId = $sell->crypto_asset_id;

        foreach ($sell->buys as $buy) {
            $buy->update([
                'quantity_remaining' => $buy->quantity_remaining + $buy->pivot->quantity,
            ]);
        }

        $sell->delete();

        return redirect("/crypto/{$assetId}")->with('success', 'Sell deleted and buy quantities restored.');
    }

    // ─── Allocate (for unallocated sells) ───

    public function allocateSell(int $id)
    {
        $sell = CryptoSell::findOrFail($id);
        $asset = $sell->asset;

        $availableBuys = CryptoBuy::where('crypto_asset_id', $asset->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('date')
            ->get();

        return view('crypto.allocate', compact('sell', 'asset', 'availableBuys'));
    }

    public function storeAllocation(Request $request, int $id)
    {
        $sell = CryptoSell::findOrFail($id);
        $asset = $sell->asset;
        $sellDate = $sell->date;

        if ($request->has('buy_allocations')) {
            $allocations = $request->buy_allocations;

            foreach ($allocations as $key => $allocation) {
                $allocations[$key]['quantity'] = $this->cleanNumeric($allocation['quantity'] ?? '0');
            }

            $request->merge(['buy_allocations' => $allocations]);
        }

        $request->validate([
            'buy_allocations' => 'required|array|min:1',
            'buy_allocations.*.buy_id' => 'required|exists:crypto_buys,id',
            'buy_allocations.*.quantity' => 'required|numeric|min:0',
        ]);

        $allocations = collect($request->buy_allocations)
            ->filter(fn($a) => $a['quantity'] > 0);

        if ($allocations->isEmpty()) {
            return back()->with('error', 'You must allocate quantity from at least one buy.')->withInput();
        }

        $totalAllocated = $allocations->sum('quantity');

        if (abs($totalAllocated - $sell->quantity) > 0.00000001) {
            return back()->with('error', "Allocated quantity ({$totalAllocated}) does not match sell quantity ({$sell->quantity}).")->withInput();
        }

        foreach ($allocations as $allocation) {
            $buy = CryptoBuy::findOrFail($allocation['buy_id']);

            if ($allocation['quantity'] > $buy->quantity_remaining) {
                return back()->with('error', "Allocation of {$allocation['quantity']} from buy on {$buy->date->format('m/d/Y')} exceeds remaining quantity of {$buy->quantity_remaining}.")->withInput();
            }
        }

        $this->processAllocations($sell, $allocations, $sellDate);

        return redirect("/crypto/{$asset->id}")->with('success', "Sell on {$sell->date->format('m/d/Y')} allocated. Gain/Loss: \${$sell->gain_loss}");
    }

    // ─── CSV Import ───

    public function importForm(int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        return view('crypto.import', compact('asset'));
    }

    public function importProcess(Request $request, int $id)
    {
        $asset = CryptoAsset::findOrFail($id);

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $contents = file_get_contents($file->getRealPath());
        $lines = preg_split('/\R/m', $contents);

        // Detect delimiter — check if tabs are more common than commas in the file
        $tabCount = substr_count($contents, "\t");
        $commaCount = substr_count($contents, ",");
        $delimiter = $tabCount > $commaCount ? "\t" : ",";

        $allRows = array_filter(
            array_map(fn($line) => str_getcsv($line, $delimiter), $lines),
            fn($row) => !empty(array_filter($row, fn($cell) => $cell !== null && $cell !== ''))
        );

        if (count($allRows) < 2) {
            return back()->with('error', 'CSV file is empty or has no data rows.');
        }

        // Scan for the header row — look for rows containing known column names
        $headerRowIndex = null;
        $format = null;
        $allRows = array_values($allRows); // re-index

        $coinbaseColumns = ['transaction type', 'date acquired', 'cost basis (usd)', 'proceeds (usd)'];
        $cashappColumns = ['transaction type', 'asset amount', 'asset price'];

        foreach ($allRows as $index => $row) {
            $lowered = array_map(fn($cell) => strtolower(trim($cell ?? '')), $row);

            // Check Coinbase first (more specific)
            $coinbaseMatches = count(array_intersect($coinbaseColumns, $lowered));
            if ($coinbaseMatches >= 3) {
                $headerRowIndex = $index;
                $format = 'coinbase';
                break;
            }

            // Check CashApp
            $cashappMatches = count(array_intersect($cashappColumns, $lowered));
            if ($cashappMatches >= 2) {
                $headerRowIndex = $index;
                $format = 'cashapp';
                break;
            }
        }

        if ($headerRowIndex === null) {
            return back()->with('error', 'Could not find a recognized header row. Supported formats: CashApp, Coinbase gain/loss report.');
        }

        $headers = array_map('trim', $allRows[$headerRowIndex]);
        $headerMap = array_flip(array_map('strtolower', $headers));
        $dataRows = array_slice($allRows, $headerRowIndex + 1);

        if ($format === 'coinbase') {
            return $this->importCoinbase($asset, $headerMap, $dataRows);
        }

        return $this->importCashApp($asset, $headerMap, $dataRows);
    }

    private function importCashApp(CryptoAsset $asset, array $headerMap, array $dataRows)
    {
        $colDate = $headerMap['date'] ?? null;
        $colType = $headerMap['transaction type'] ?? null;
        $colAmount = $headerMap['asset amount'] ?? null;
        $colPrice = $headerMap['asset price'] ?? null;
        $colFee = $headerMap['fee'] ?? null;

        if ($colDate === null || $colType === null || $colAmount === null || $colPrice === null) {
            return back()->with('error', 'CashApp format: could not find required columns (Date, Transaction Type, Asset Amount, Asset Price).');
        }

        $buysCreated = 0;
        $sellsCreated = 0;
        $skipped = 0;

        foreach ($dataRows as $row) {
            $type = strtolower(trim($row[$colType] ?? ''));
            $date = trim($row[$colDate] ?? '');
            $amount = abs((float) $this->cleanNumeric($row[$colAmount] ?? '0'));
            $price = abs((float) $this->cleanNumeric($row[$colPrice] ?? '0'));
            $fee = abs((float) $this->cleanNumeric($row[$colFee] ?? '0'));

            if (empty($date) || $amount <= 0) {
                $skipped++;
                continue;
            }

            $parsedDate = $this->parseDate($date);

            if (!$parsedDate) {
                $skipped++;
                continue;
            }

            if (str_contains($type, 'buy') || str_contains($type, 'purchase')) {
                $totalCost = round(($amount * $price) + $fee, 2);

                CryptoBuy::create([
                    'crypto_asset_id' => $asset->id,
                    'date' => $parsedDate,
                    'quantity' => $amount,
                    'cost_per_unit' => $price,
                    'total_cost' => $totalCost,
                    'fee' => $fee,
                    'quantity_remaining' => $amount,
                ]);

                $buysCreated++;
            } elseif (str_contains($type, 'sell') || str_contains($type, 'sale')) {
                $totalProceeds = round(($amount * $price) - $fee, 2);

                CryptoSell::create([
                    'crypto_asset_id' => $asset->id,
                    'date' => $parsedDate,
                    'quantity' => $amount,
                    'price_per_unit' => $price,
                    'total_proceeds' => $totalProceeds,
                    'fee' => $fee,
                ]);

                $sellsCreated++;
            } else {
                $skipped++;
            }
        }

        $message = "CashApp import complete: {$buysCreated} buys, {$sellsCreated} sells.";

        if ($skipped > 0) {
            $message .= " {$skipped} rows skipped.";
        }

        if ($sellsCreated > 0) {
            $message .= " Sells need buy allocation — see unallocated sells below.";
        }

        return redirect("/crypto/{$asset->id}")->with('success', $message);
    }

    private function importCoinbase(CryptoAsset $asset, array $headerMap, array $dataRows)
    {
        $colType = $headerMap['transaction type'] ?? null;
        $colAmount = $headerMap['amount'] ?? null;
        $colDateAcquired = $headerMap['date acquired'] ?? null;
        $colCostBasis = $headerMap['cost basis (usd)'] ?? null;
        $colDateDisposition = $headerMap['date of disposition'] ?? null;
        $colProceeds = $headerMap['proceeds (usd)'] ?? null;
        $colGainLoss = $headerMap['gains (losses) (usd)'] ?? null;
        $colHoldingPeriod = $headerMap['holding period (days)'] ?? null;

        if ($colAmount === null || $colDateDisposition === null || $colProceeds === null || $colCostBasis === null) {
            return back()->with('error', 'Coinbase format: could not find required columns (Amount, Date of Disposition, Proceeds (USD), Cost basis (USD)).');
        }

        $pairsCreated = 0;
        $skipped = 0;

        foreach ($dataRows as $row) {
            $type = strtolower(trim($row[$colType] ?? 'sell'));
            $amount = abs((float) $this->cleanNumeric($row[$colAmount] ?? '0'));
            $costBasis = abs((float) $this->cleanNumeric($row[$colCostBasis] ?? '0'));
            $proceeds = abs((float) $this->cleanNumeric($row[$colProceeds] ?? '0'));
            $gainLoss = (float) $this->cleanNumeric($row[$colGainLoss] ?? '0');
            $holdingDays = (int) ($row[$colHoldingPeriod] ?? 0);

            $sellDateStr = trim($row[$colDateDisposition] ?? '');
            $buyDateStr = $colDateAcquired !== null ? trim($row[$colDateAcquired] ?? '') : '';

            if (empty($sellDateStr) || $amount <= 0) {
                $skipped++;
                continue;
            }

            $parsedSellDate = $this->parseDate($sellDateStr);

            if (!$parsedSellDate) {
                $skipped++;
                continue;
            }

            // Determine buy date: from column, or calculate from holding period
            $parsedBuyDate = null;

            if (!empty($buyDateStr)) {
                $parsedBuyDate = $this->parseDate($buyDateStr);
            }

            if (!$parsedBuyDate && $holdingDays > 0) {
                $parsedBuyDate = Carbon::parse($parsedSellDate)->subDays($holdingDays)->format('Y-m-d');
            }

            if (!$parsedBuyDate) {
                $skipped++;
                continue;
            }

            // Skip non-disposition types
            if (!str_contains($type, 'sell') && !str_contains($type, 'convert') && $type !== '') {
                // If type is empty, treat as sell (some exports omit it)
                if ($type !== '') {
                    $skipped++;
                    continue;
                }
            }

            $isLongTerm = $holdingDays > 365;
            $costPerUnit = $amount > 0 ? round($costBasis / $amount, 2) : 0;
            $pricePerUnit = $amount > 0 ? round($proceeds / $amount, 2) : 0;

            // Create the buy (tax lot origin)
            $buy = CryptoBuy::create([
                'crypto_asset_id' => $asset->id,
                'date' => $parsedBuyDate,
                'quantity' => $amount,
                'cost_per_unit' => $costPerUnit,
                'total_cost' => $costBasis,
                'fee' => 0,
                'quantity_remaining' => 0, // fully consumed by this sell
                'notes' => 'Coinbase tax lot import',
            ]);

            // Create the sell
            $sell = CryptoSell::create([
                'crypto_asset_id' => $asset->id,
                'date' => $parsedSellDate,
                'quantity' => $amount,
                'price_per_unit' => $pricePerUnit,
                'total_proceeds' => $proceeds,
                'fee' => 0,
                'total_cost_basis' => $costBasis,
                'gain_loss' => $gainLoss,
                'notes' => 'Coinbase gain/loss import',
            ]);

            // Create the allocation linking them
            $sell->buys()->attach($buy->id, [
                'quantity' => $amount,
                'cost_basis' => $costBasis,
                'is_long_term' => $isLongTerm,
            ]);

            $pairsCreated++;
        }

        $message = "Coinbase import complete: {$pairsCreated} sell transaction" . ($pairsCreated !== 1 ? 's' : '') . " with cost basis fully allocated.";

        if ($skipped > 0) {
            $message .= " {$skipped} rows skipped.";
        }

        return redirect("/crypto/{$asset->id}")->with('success', $message);
    }

    // ─── Helpers ───

    private function processAllocations(CryptoSell $sell, $allocations, $sellDate): void
    {
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

            $buy->update([
                'quantity_remaining' => $buy->quantity_remaining - $allocationQty,
            ]);

            $totalCostBasis += $costBasis;
        }

        $sell->update([
            'total_cost_basis' => $totalCostBasis,
            'gain_loss' => $sell->total_proceeds - $totalCostBasis,
        ]);
    }

    private function cleanNumeric(?string $value): string
    {
        if ($value === null) {
            return '0';
        }

        return preg_replace('/[^0-9.\-]/', '', $value);
    }

    private function parseDate(string $date): ?string
    {
        $formats = [
            'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d',
            'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y',
            'M d, Y', 'M j, Y', 'F d, Y', 'F j, Y',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, trim($date));

            if ($parsed) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime(trim($date)))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}