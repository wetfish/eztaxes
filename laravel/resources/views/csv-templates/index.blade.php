@extends('layouts.app')

@section('title', 'CSV Templates - EzTaxes')

@section('content')
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold">CSV Templates</h1>
    </div>

    @if($templates->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No templates yet.</p>
            <p class="text-sm mt-2">Templates are created automatically when you import a CSV and save the column mapping.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($templates as $template)
                <div class="bg-white border border-stone-200 rounded-lg p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="font-bold">{{ $template->name }}</h2>
                                @if($template->is_seeded)
                                    <span class="text-xs bg-stone-100 text-stone-500 px-2 py-0.5 rounded">Built-in</span>
                                @endif
                            </div>
                            @if($template->detection_headers)
                                <p class="text-xs text-stone-400 mt-1">
                                    Auto-detects: {{ implode(', ', $template->detection_headers) }}
                                </p>
                            @endif
                        </div>
                        @if(!$template->is_seeded)
                            <form action="{{ url('/csv-templates/' . $template->id) }}" method="POST" onsubmit="return confirm('Delete this template?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection