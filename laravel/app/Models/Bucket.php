<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bucket extends Model
{
    protected $fillable = [
        'bucket_group_id',
        'name',
        'slug',
        'behavior',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(BucketGroup::class, 'bucket_group_id');
    }

    public function patterns(): HasMany
    {
        return $this->hasMany(BucketPattern::class);
    }

    public function scheduleLines(): HasMany
    {
        return $this->hasMany(BucketScheduleLine::class);
    }

    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'bucket_transaction')
            ->withPivot(['assigned_via', 'bucket_pattern_id'])
            ->withTimestamps();
    }
}