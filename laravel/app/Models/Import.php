<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    protected $fillable = [
        'tax_year_id',
        'csv_template_id',
        'original_filename',
        'rows_total',
        'rows_matched',
        'rows_unmatched',
        'rows_ignored',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'rows_total' => 'integer',
            'rows_matched' => 'integer',
            'rows_unmatched' => 'integer',
            'rows_ignored' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    public function taxYear(): BelongsTo
    {
        return $this->belongsTo(TaxYear::class);
    }

    public function csvTemplate(): BelongsTo
    {
        return $this->belongsTo(CsvTemplate::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}