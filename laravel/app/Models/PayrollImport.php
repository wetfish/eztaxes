<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollImport extends Model
{
    protected $fillable = [
        'tax_year_id',
        'csv_template_id',
        'type',
        'original_filename',
        'rows_total',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
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

    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}