@extends('layouts.app')

@section('title', 'Buckets - eztaxes')

@section('content')
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold">Buckets</h1>
    </div>

    {{-- Create Bucket --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-8">
        <h2 class="font-medium mb-3">Create New Bucket</h2>
        <form action="{{ url('/buckets') }}" method="POST" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="block text-xs font-medium text-stone-500 mb-1">Name</label>
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
            <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Create
            </button>
        </form>
    </div>

    @if($buckets->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No buckets yet.</p>
            <p class="text-sm mt-2">Import legacy buckets via the artisan command or create one above.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($buckets as $bucket)
                <div class="bg-white border border-stone-200 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-3">
                            <h2 class="font-bold">{{ $bucket->name }}</h2>
                            <span class="text-xs text-stone-400">{{ $bucket->slug }}</span>
                            @if($bucket->behavior !== 'normal')
                                <span class="text-xs px-2 py-0.5 rounded bg-stone-100 text-stone-500">{{ $bucket->behavior }}</span>
                            @endif
                            @unless($bucket->is_active)
                                <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-600">inactive</span>
                            @endunless
                        </div>
                        <form action="{{ url('/buckets/' . $bucket->id) }}" method="POST" onsubmit="return confirm('Delete this bucket and all its patterns?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                        </form>
                    </div>

                    @if($bucket->description)
                        <p class="text-sm text-stone-500 mb-3">{{ $bucket->description }}</p>
                    @endif

                    @if($bucket->scheduleLines->isNotEmpty())
                        <div class="mb-3">
                            @foreach($bucket->scheduleLines as $line)
                                <span class="inline-block text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded mr-1">
                                    {{ $line->form_name }} {{ $line->line_reference }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Patterns --}}
                    <details class="border-t border-stone-100 pt-3 mt-3">
                        <summary class="text-xs text-stone-400 cursor-pointer hover:text-stone-600 select-none">
                            {{ $bucket->patterns->count() }} pattern{{ $bucket->patterns->count() !== 1 ? 's' : '' }}
                        </summary>
                        <div class="mt-3 space-y-2">
                            @foreach($bucket->patterns as $pattern)
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <code class="text-xs bg-stone-50 border border-stone-200 px-2 py-1 rounded truncate {{ !$pattern->is_active ? 'opacity-40' : '' }}">{{ $pattern->pattern }}</code>
                                        @if($pattern->description)
                                            <span class="text-xs text-stone-400 truncate">{{ $pattern->description }}</span>
                                        @endif
                                    </div>
                                    <form action="{{ url('/patterns/' . $pattern->id) }}" method="POST" onsubmit="return confirm('Delete this pattern?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs whitespace-nowrap">Delete</button>
                                    </form>
                                </div>
                            @endforeach

                            {{-- Add Pattern --}}
                            <form action="{{ url('/buckets/' . $bucket->id . '/patterns') }}" method="POST" class="flex items-end gap-2 pt-2 border-t border-stone-100">
                                @csrf
                                <div class="flex-1">
                                    <input type="text" name="pattern" required placeholder="Regex pattern" class="border border-stone-300 rounded px-2 py-1 text-xs w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                                </div>
                                <div class="flex-1">
                                    <input type="text" name="description" placeholder="Description (optional)" class="border border-stone-300 rounded px-2 py-1 text-xs w-full focus:outline-none focus:ring-2 focus:ring-stone-400">
                                </div>
                                <button type="submit" class="bg-stone-800 text-white px-3 py-1 rounded text-xs hover:bg-stone-700 transition-colors whitespace-nowrap">
                                    Add
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            @endforeach
        </div>
    @endif
@endsection