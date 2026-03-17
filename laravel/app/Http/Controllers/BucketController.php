<?php

namespace App\Http\Controllers;

use App\Models\Bucket;

class BucketController extends Controller
{
    public function index()
    {
        $buckets = Bucket::with(['patterns', 'scheduleLines'])
            ->orderBy('sort_order')
            ->get();

        return view('buckets.index', compact('buckets'));
    }
}