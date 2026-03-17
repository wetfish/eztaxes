<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BucketScheduleLine extends Model
{
    protected $fillable = [
        'bucket_id',
        'form_name',
        'line_reference',
        'description',
    ];

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }
}