<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alpha Vantage (Stock Prices)
    |--------------------------------------------------------------------------
    |
    | Free API key from https://www.alphavantage.co/support/#api-key
    | 25 requests per day on the free tier.
    |
    */
    'alphavantage' => [
        'api_key' => env('ALPHAVANTAGE_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CryptoCompare (Crypto Prices)
    |--------------------------------------------------------------------------
    |
    | No API key required for basic historical price lookups.
    | Free tier has IP-based rate limiting.
    |
    */

];