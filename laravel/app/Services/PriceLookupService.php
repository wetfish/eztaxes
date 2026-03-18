<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriceLookupService
{
    /**
     * Fetch historical crypto price from CryptoCompare for a specific date.
     * No API key required.
     *
     * @param string $symbol e.g. "BTC", "ETH"
     * @param string $date Format: Y-m-d
     * @return float|null Price in USD, or null on failure
     */
    public function getCryptoPrice(string $symbol, string $date): ?float
    {
        $timestamp = strtotime($date . ' 23:59:59 UTC');

        try {
            $response = Http::get('https://min-api.cryptocompare.com/data/pricehistorical', [
                'fsym' => strtoupper($symbol),
                'tsyms' => 'USD',
                'ts' => $timestamp,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data[strtoupper($symbol)]['USD'] ?? null;
            }

            Log::warning("CryptoCompare API error for {$symbol}: " . $response->status());
            return null;
        } catch (\Exception $e) {
            Log::error("CryptoCompare API exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch historical stock price from Alpha Vantage for a specific date.
     * Requires a free API key (25 calls/day).
     *
     * @param string $symbol e.g. "RBLX", "BLK", "DOCN"
     * @param string $date Format: Y-m-d
     * @return float|null Closing price in USD, or null on failure
     */
    public function getStockPrice(string $symbol, string $date): ?float
    {
        $apiKey = config('price_apis.alphavantage.api_key');

        if (empty($apiKey)) {
            Log::warning('Alpha Vantage API key not configured. Add ALPHAVANTAGE_API_KEY to .env');
            return null;
        }

        try {
            $response = Http::get('https://www.alphavantage.co/query', [
                'function' => 'TIME_SERIES_DAILY',
                'symbol' => strtoupper($symbol),
                'outputsize' => 'compact',
                'apikey' => $apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Detect rate limiting — Alpha Vantage returns 200 with a Note or Information key
                if (isset($data['Note']) || isset($data['Information'])) {
                    $message = $data['Note'] ?? $data['Information'];
                    Log::warning("Alpha Vantage rate limited for {$symbol}: {$message}");
                    return null;
                }

                $timeSeries = $data['Time Series (Daily)'] ?? [];

                if (empty($timeSeries)) {
                    Log::warning("Alpha Vantage: empty time series for {$symbol}. Response: " . json_encode(array_keys($data)));
                    return null;
                }

                // Look for exact date match
                if (isset($timeSeries[$date])) {
                    return (float) $timeSeries[$date]['4. close'];
                }

                // If Dec 31 was a weekend/holiday, find the closest preceding trading day
                $targetDate = strtotime($date);

                foreach ($timeSeries as $tsDate => $values) {
                    if (strtotime($tsDate) <= $targetDate) {
                        return (float) $values['4. close'];
                    }
                }

                Log::warning("Alpha Vantage: no data found near {$date} for {$symbol}");
                return null;
            }

            Log::warning("Alpha Vantage API error for {$symbol}: " . $response->status());
            return null;
        } catch (\Exception $e) {
            Log::error("Alpha Vantage API exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch price for a balance sheet item based on its type.
     *
     * @param string $assetType "crypto" or "stock"
     * @param string|null $identifier Crypto symbol (BTC, ETH) or stock ticker (RBLX, BLK)
     * @param string $date Format: Y-m-d
     * @return float|null
     */
    public function getPrice(string $assetType, ?string $identifier, string $date): ?float
    {
        if (empty($identifier)) {
            return null;
        }

        return match ($assetType) {
            'crypto' => $this->getCryptoPrice($identifier, $date),
            'stock' => $this->getStockPrice($identifier, $date),
            default => null,
        };
    }
}