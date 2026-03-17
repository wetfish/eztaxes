<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxYear extends Model
{
    protected $fillable = [
        'year',
        'filing_status',
        'total_income',
        'total_expenses',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total_income' => 'decimal:2',
            'total_expenses' => 'decimal:2',
        ];
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}