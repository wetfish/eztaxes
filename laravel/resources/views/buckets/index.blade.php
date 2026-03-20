@extends('layouts.app')

@section('title', 'Buckets - EzTaxes')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <h1 class="text-2xl font-bold">Buckets</h1>
    </div>

    {{-- Create Forms --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
        {{-- Create Group --}}
        <div class="bg-white border border-stone-200 rounded-lg p-5">
            <h2 class="font-medium mb-3">Create Group</h2>
            <form action="{{ url('/bucket-groups') }}" method="POST" class="grid grid-cols-1 sm:flex sm:items-end gap-3">
                @csrf
                <div class="sm:flex-1">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Group Name</label>
                    <input type="text" name="name" required placeholder="e.g. Client Income" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                </div>
                <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors whitespace-nowrap">
                    Create
                </button>
            </form>
        </div>

        {{-- Create Bucket --}}
        <div class="bg-white border border-stone-200 rounded-lg p-5">
            <h2 class="font-medium mb-3">Create Bucket</h2>
            <form action="{{ url('/buckets') }}" method="POST" class="grid grid-cols-1 sm:flex sm:items-end gap-3">
                @csrf
                <div class="sm:flex-1">
                    <label class="block text-xs font-medium text-stone-500 mb-1">Bucket Name</label>
                    <input type="text" name="name" required placeholder="e.g. Office Supplies" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-stone-500 mb-1">Behavior</label>
                    <select name="behavior" class="border border-stone-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
                        <option value="normal">Normal</option>
                        <option value="ignored">Ignored</option>
                        <option value="informational">Informational</option>
                    </select>
                </div>
                @if($groups->isNotEmpty())
                    <div>
                        <label class="block text-xs font-medium text-stone-500 mb-1">Group</label>
                        <select name="bucket_group_id" class="border border-stone-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-stone-400">
                            <option value="">None</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors whitespace-nowrap">
                    Create
                </button>
            </form>
        </div>
    </div>

    {{-- Bucket Groups --}}
    @foreach($groups as $group)
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-3">
                    <h2 class="text-lg font-bold text-stone-700">{{ $group->name }}</h2>
                    <span class="text-xs text-stone-400">{{ $group->buckets->count() }} bucket{{ $group->buckets->count() !== 1 ? 's' : '' }}</span>
                </div>
                <form action="{{ url('/bucket-groups/' . $group->id) }}" method="POST" onsubmit="return confirm('Delete this group? Its buckets will become unassigned.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete Group</button>
                </form>
            </div>

            @if($group->buckets->isEmpty())
                <div class="bg-stone-50 border border-stone-200 rounded-lg p-4 text-sm text-stone-400">
                    No buckets in this group yet. Assign buckets below or create a new one.
                </div>
            @else
                <div class="grid gap-3">
                    @foreach($group->buckets as $bucket)
                        @include('buckets._bucket-card', ['bucket' => $bucket, 'groups' => $groups, 'currentGroupId' => $group->id])
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    {{-- Unassigned Buckets --}}
    @if($unassigned->isNotEmpty())
        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-bold text-amber-700">Unassigned Buckets</h2>
                <span class="text-xs text-amber-600">Assign these to a group for organized reporting</span>
            </div>

            <div class="grid gap-3">
                @foreach($unassigned as $bucket)
                    @include('buckets._bucket-card', ['bucket' => $bucket, 'groups' => $groups, 'currentGroupId' => null])
                @endforeach
            </div>
        </div>
    @endif

    @if($groups->isEmpty() && $unassigned->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No buckets yet.</p>
            <p class="text-sm mt-2">Import legacy buckets or create groups and buckets above.</p>
        </div>
    @endif
@endsection