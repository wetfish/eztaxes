<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\BucketPattern;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BucketController extends Controller
{
    public function index()
    {
        $buckets = Bucket::with(['patterns', 'scheduleLines'])
            ->orderBy('sort_order')
            ->get();

        return view('buckets.index', compact('buckets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'behavior' => 'required|in:normal,ignored,informational',
        ]);

        $slug = Str::slug($request->name);

        if (Bucket::where('slug', $slug)->exists()) {
            return back()->with('error', "A bucket with the slug '{$slug}' already exists.");
        }

        $maxSort = Bucket::max('sort_order') ?? 0;

        Bucket::create([
            'name' => $request->name,
            'slug' => $slug,
            'behavior' => $request->behavior,
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return redirect('/buckets')->with('success', "Bucket '{$request->name}' created.");
    }

    public function destroy(int $id)
    {
        $bucket = Bucket::findOrFail($id);
        $name = $bucket->name;

        $bucket->delete();

        return redirect('/buckets')->with('success', "Bucket '{$name}' deleted.");
    }

    public function addPattern(Request $request, int $id)
    {
        $bucket = Bucket::findOrFail($id);

        $request->validate([
            'pattern' => 'required|string|max:500',
            'description' => 'nullable|string|max:255',
        ]);

        // Validate that the pattern is a valid regex
        if (@preg_match('/' . $request->pattern . '/i', '') === false) {
            return back()->with('error', "Invalid regex pattern: {$request->pattern}");
        }

        $maxPriority = $bucket->patterns()->max('priority') ?? 0;

        BucketPattern::create([
            'bucket_id' => $bucket->id,
            'pattern' => $request->pattern,
            'priority' => $maxPriority + 1,
            'description' => $request->description,
            'is_active' => true,
        ]);

        return redirect('/buckets')->with('success', "Pattern added to '{$bucket->name}'.");
    }

    public function deletePattern(int $id)
    {
        $pattern = BucketPattern::findOrFail($id);
        $bucketName = $pattern->bucket->name;

        $pattern->delete();

        return redirect('/buckets')->with('success', "Pattern removed from '{$bucketName}'.");
    }
}