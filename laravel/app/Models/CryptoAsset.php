<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoAsset extends Model
{
    protected $fillable = [
        'name',
        'symbol',
    ];

    public function buys(): HasMany
    {
        return $this->hasMany(CryptoBuy::class);
    }

    public function sells(): HasMany
    {
        return $this->hasMany(CryptoSell::class);
    }
}