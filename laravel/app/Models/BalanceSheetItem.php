<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceSheetItem extends Model
{
    protected $fillable = [
        'tax_year_id',
        'crypto_asset_id',
        'label',
        'asset_type',
        'ticker_symbol',
        'quantity',
        'unit_price_year_end',
        'total_value',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:8',
            'unit_price_year_end' => 'decimal:2',
            'total_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class);
    }

    public function cryptoAsset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class);
    }
}