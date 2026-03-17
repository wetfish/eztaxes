<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CsvTemplate extends Model
{
    protected $fillable = [
        'name',
        'column_mapping',
    ];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
        ];
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }
}