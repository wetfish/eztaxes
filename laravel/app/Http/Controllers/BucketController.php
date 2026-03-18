<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\BucketGroup;
use App\Models\BucketPattern;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BucketController extends Controller
{
    public function index()
    {
        $groups = BucketGroup::with(['buckets' => function ($query) {
            $query->with(['patterns', 'scheduleLines'])->orderBy('sort_order');
        }])
            ->orderBy('sort_order')
            ->get();

        $unassigned = Bucket::whereNull('bucket_group_id')
            ->with(['patterns', 'scheduleLines'])
            ->orderBy('sort_order')
            ->get();

        return view('buckets.index', compact('groups', 'unassigned'));
    }

    // ─── Groups ───

    public function storeGroup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $slug = Str::slug($request->name);

        if (BucketGroup::where('slug', $slug)->exists()) {
            return back()->with('error', "A group with the slug '{$slug}' already exists.");
        }

        $maxSort = BucketGroup::max('sort_order') ?? 0;

        BucketGroup::create([
            'name' => $request->name,
            'slug' => $slug,
            'sort_order' => $maxSort + 1,
        ]);

        return redirect('/buckets')->with('success', "Group '{$request->name}' created.");
    }

    public function destroyGroup(int $id)
    {
        $group = BucketGroup::findOrFail($id);
        $name = $group->name;

        // Unassign all buckets in this group
        Bucket::where('bucket_group_id', $group->id)->update(['bucket_group_id' => null]);

        $group->delete();

        return redirect('/buckets')->with('success', "Group '{$name}' deleted. Its buckets are now unassigned.");
    }

    // ─── Buckets ───

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'behavior' => 'required|in:normal,ignored,informational',
            'bucket_group_id' => 'nullable|exists:bucket_groups,id',
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
            'bucket_group_id' => $request->bucket_group_id,
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return redirect('/buckets')->with('success', "Bucket '{$request->name}' created.");
    }

    public function updateGroup(Request $request, int $id)
    {
        $bucket = Bucket::findOrFail($id);

        $request->validate([
            'bucket_group_id' => 'nullable|exists:bucket_groups,id',
        ]);

        $bucket->update(['bucket_group_id' => $request->bucket_group_id ?: null]);

        if ($request->bucket_group_id) {
            $group = BucketGroup::find($request->bucket_group_id);
            return redirect('/buckets')->with('success', "'{$bucket->name}' moved to '{$group->name}'.");
        }

        return redirect('/buckets')->with('success', "'{$bucket->name}' removed from group.");
    }

    public function destroy(int $id)
    {
        $bucket = Bucket::findOrFail($id);
        $name = $bucket->name;

        $bucket->delete();

        return redirect('/buckets')->with('success', "Bucket '{$name}' deleted.");
    }

    // ─── Patterns ───

    public function addPattern(Request $request, int $id)
    {
        $bucket = Bucket::findOrFail($id);

        $request->validate([
            'pattern' => 'required|string|max:500',
            'description' => 'nullable|string|max:255',
        ]);

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