@extends('layouts.app')

@section('title', 'Buckets - eztaxes')

@section('content')
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold">Buckets</h1>
    </div>

    @if($buckets->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No buckets yet.</p>
            <p class="text-sm mt-2">Import legacy buckets via the artisan command to get started.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($buckets as $bucket)
                <div class="bg-white border border-stone-200 rounded-lg p-5">
                    <div class="flex items-center mb-2">
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

                    @if($bucket->patterns->isNotEmpty())
                        <details class="border-t border-stone-100 pt-3 mt-3">
                            <summary class="text-xs text-stone-400 cursor-pointer hover:text-stone-600 select-none">
                                {{ $bucket->patterns->count() }} pattern{{ $bucket->patterns->count() !== 1 ? 's' : '' }}
                            </summary>
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach($bucket->patterns as $pattern)
                                    <code class="text-xs bg-stone-50 border border-stone-200 px-2 py-1 rounded {{ !$pattern->is_active ? 'opacity-40' : '' }}">{{ $pattern->pattern }}</code>
                                @endforeach
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection