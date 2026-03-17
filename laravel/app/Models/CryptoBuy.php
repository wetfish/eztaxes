<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CryptoBuy extends Model
{
    protected $fillable = [
        'crypto_asset_id',
        'date',
        'quantity',
        'cost_per_unit',
        'total_cost',
        'fee',
        'quantity_remaining',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:8',
            'cost_per_unit' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'fee' => 'decimal:2',
            'quantity_remaining' => 'decimal:8',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'crypto_asset_id');
    }

    public function sells(): BelongsToMany
    {
        return $this->belongsToMany(CryptoSell::class, 'crypto_buy_sell')
            ->withPivot(['quantity', 'cost_basis', 'is_long_term'])
            ->withTimestamps();
    }
}