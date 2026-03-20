<div class="bg-white border {{ $currentGroupId ? 'border-stone-200' : 'border-amber-200' }} rounded-lg p-5">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <h3 class="font-bold">{{ $bucket->name }}</h3>
            <span class="text-xs text-stone-400">{{ $bucket->slug }}</span>
            @if($bucket->behavior !== 'normal')
                <span class="text-xs px-2 py-0.5 rounded bg-stone-100 text-stone-500">{{ $bucket->behavior }}</span>
            @endif
            @unless($bucket->is_active)
                <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-600">inactive</span>
            @endunless
        </div>
        <div class="flex items-center gap-4">
            @if($groups->isNotEmpty())
                <form action="{{ url('/buckets/' . $bucket->id . '/group') }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <select name="bucket_group_id" class="border border-stone-300 rounded px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-stone-400">
                        <option value="">No group</option>
                        @foreach($groups as $g)
                            <option value="{{ $g->id }}" {{ $currentGroupId == $g->id ? 'selected' : '' }}>{{ $g->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="text-stone-500 hover:text-stone-700 text-xs">{{ $currentGroupId ? 'Move' : 'Assign' }}</button>
                </form>
            @endif
            <form action="{{ url('/buckets/' . $bucket->id) }}" method="POST" onsubmit="return confirm('Delete this bucket and all its patterns?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
            </form>
        </div>
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