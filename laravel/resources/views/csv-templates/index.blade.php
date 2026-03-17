@extends('layouts.app')

@section('title', 'CSV Templates - eztaxes')

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
                            <h2 class="font-bold">{{ $template->name }}</h2>
                            <div class="text-sm text-stone-500 mt-1">
                                @foreach($template->column_mapping as $field => $index)
                                    <span class="inline-block mr-3">{{ ucfirst($field) }}: column {{ $index }}</span>
                                @endforeach
                            </div>
                        </div>
                        <form action="{{ url('/csv-templates/' . $template->id) }}" method="POST" onsubmit="return confirm('Delete this template?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection