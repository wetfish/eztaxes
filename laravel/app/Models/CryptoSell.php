<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CryptoSell extends Model
{
    protected $fillable = [
        'crypto_asset_id',
        'date',
        'quantity',
        'price_per_unit',
        'total_proceeds',
        'total_cost_basis',
        'gain_loss',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:8',
            'price_per_unit' => 'decimal:2',
            'total_proceeds' => 'decimal:2',
            'total_cost_basis' => 'decimal:2',
            'gain_loss' => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(CryptoAsset::class, 'crypto_asset_id');
    }

    public function buys(): BelongsToMany
    {
        return $this->belongsToMany(CryptoBuy::class, 'crypto_buy_sell')
            ->withPivot(['quantity', 'cost_basis', 'is_long_term'])
            ->withTimestamps();
    }
}