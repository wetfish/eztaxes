<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvTemplate extends Model
{
    protected $fillable = [
        'name',
        'column_mapping',
        'detection_headers',
        'is_seeded',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'detection_headers' => 'array',
            'is_seeded' => 'boolean',
        ];
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    /**
     * Try to match this template against a set of CSV headers.
     * Returns true if ALL detection_headers are found in the given headers.
     */
    public function matchesHeaders(array $csvHeaders): bool
    {
        if (empty($this->detection_headers)) {
            return false;
        }

        $lowered = array_map(fn($h) => strtolower(trim($h)), $csvHeaders);

        foreach ($this->detection_headers as $required) {
            if (!in_array(strtolower($required), $lowered)) {
                return false;
            }
        }

        return true;
    }
}