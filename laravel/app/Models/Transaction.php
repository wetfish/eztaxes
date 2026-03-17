<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Transaction extends Model
{
    protected $fillable = [
        'tax_year_id',
        'import_id',
        'date',
        'description',
        'amount',
        'match_type',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function buckets(): BelongsToMany
    {
        return $this->belongsToMany(Bucket::class, 'bucket_transaction')
            ->withPivot(['assigned_via', 'bucket_pattern_id'])
            ->withTimestamps();
    }
}