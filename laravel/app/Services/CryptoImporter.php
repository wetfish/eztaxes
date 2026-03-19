<?php

namespace App\Services;

use App\Models\CryptoAsset;
use App\Models\CryptoBuy;
use App\Models\CryptoSell;
use Illuminate\Support\Carbon;

class CryptoImporter
{
    /**
     * Detect the crypto import format from parsed rows.
     * Returns 'cashapp', 'coinbase', or null.
     */
    public function detectFormat(array $rows, int $headerRowIndex): ?string
    {
        $row = $rows[$headerRowIndex] ?? [];
        $lowered = array_map(fn($cell) => strtolower(trim($cell ?? '')), $row);

        $coinbaseColumns = ['transaction type', 'date acquired', 'cost basis (usd)', 'proceeds (usd)'];
        $cashappColumns = ['transaction type', 'asset amount', 'asset price'];

        if (count(array_intersect($coinbaseColumns, $lowered)) >= 3) {
            return 'coinbase';
        }

        if (count(array_intersect($cashappColumns, $lowered)) >= 2) {
            return 'cashapp';
        }

        return null;
    }

    /**
     * Try to detect the crypto asset symbol from data rows.
     * Returns the symbol string or null.
     */
    public function detectAssetSymbol(array $rows, int $headerRowIndex): ?string
    {
        $headers = $rows[$headerRowIndex] ?? [];
        $lowered = array_map(fn($h) => strtolower(trim($h ?? '')), $headers);
        $dataRows = array_slice($rows, $headerRowIndex + 1, 10);

        // Look for an asset name/symbol column
        $assetColAliases = ['asset name', 'asset', 'asset currency', 'symbol', 'currency name'];
        $assetColIndex = null;

        foreach ($lowered as $idx => $header) {
            if (in_array($header, $assetColAliases)) {
                $assetColIndex = $idx;
                break;
            }
        }

        if ($assetColIndex === null) {
            return null;
        }

        // Collect symbols from data rows and return the most common one
        $symbols = [];

        foreach ($dataRows as $row) {
            $val = strtoupper(trim($row[$assetColIndex] ?? ''));

            if (!empty($val)) {
                $symbols[] = $val;
            }
        }

        if (empty($symbols)) {
            return null;
        }

        $counts = array_count_values($symbols);
        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * Import a CashApp crypto CSV.
     *
     * @return array{buys: int, sells: int, skipped: int}
     */
    public function importCashApp(CryptoAsset $asset, array $rows, int $headerRowIndex): array
    {
        $headers = array_map('trim', $rows[$headerRowIndex]);
        $headerMap = array_flip(array_map('strtolower', $headers));
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $colDate = $headerMap['date'] ?? null;
        $colType = $headerMap['transaction type'] ?? null;
        $colAmount = $headerMap['asset amount'] ?? null;
        $colPrice = $headerMap['asset price'] ?? null;
        $colFee = $headerMap['fee'] ?? null;

        if ($colDate === null || $colType === null || $colAmount === null || $colPrice === null) {
            throw new \RuntimeException('CashApp format: could not find required columns (Date, Transaction Type, Asset Amount, Asset Price).');
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

        return ['buys' => $buysCreated, 'sells' => $sellsCreated, 'skipped' => $skipped];
    }

    /**
     * Import a Coinbase gain/loss CSV.
     *
     * @return array{pairs: int, skipped: int}
     */
    public function importCoinbase(CryptoAsset $asset, array $rows, int $headerRowIndex): array
    {
        $headers = array_map('trim', $rows[$headerRowIndex]);
        $headerMap = array_flip(array_map('strtolower', $headers));
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $colType = $headerMap['transaction type'] ?? null;
        $colAmount = $headerMap['amount'] ?? null;
        $colDateAcquired = $headerMap['date acquired'] ?? null;
        $colCostBasis = $headerMap['cost basis (usd)'] ?? null;
        $colDateDisposition = $headerMap['date of disposition'] ?? null;
        $colProceeds = $headerMap['proceeds (usd)'] ?? null;
        $colGainLoss = $headerMap['gains (losses) (usd)'] ?? null;
        $colHoldingPeriod = $headerMap['holding period (days)'] ?? null;

        if ($colAmount === null || $colDateDisposition === null || $colProceeds === null || $colCostBasis === null) {
            throw new \RuntimeException('Coinbase format: could not find required columns (Amount, Date of Disposition, Proceeds (USD), Cost basis (USD)).');
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
                if ($type !== '') {
                    $skipped++;
                    continue;
                }
            }

            $isLongTerm = $holdingDays > 365;
            $costPerUnit = $amount > 0 ? round($costBasis / $amount, 2) : 0;
            $pricePerUnit = $amount > 0 ? round($proceeds / $amount, 2) : 0;

            $buy = CryptoBuy::create([
                'crypto_asset_id' => $asset->id,
                'date' => $parsedBuyDate,
                'quantity' => $amount,
                'cost_per_unit' => $costPerUnit,
                'total_cost' => $costBasis,
                'fee' => 0,
                'quantity_remaining' => 0,
                'notes' => 'Coinbase tax lot import',
            ]);

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

            $sell->buys()->attach($buy->id, [
                'quantity' => $amount,
                'cost_basis' => $costBasis,
                'is_long_term' => $isLongTerm,
            ]);

            $pairsCreated++;
        }

        return ['pairs' => $pairsCreated, 'skipped' => $skipped];
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