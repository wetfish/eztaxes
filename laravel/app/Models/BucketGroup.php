<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BucketGroup extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function buckets(): HasMany
    {
        return $this->hasMany(Bucket::class)->orderBy('sort_order');
    }
}