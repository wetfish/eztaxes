@extends('layouts.app')

@section('title', 'Import CSV - ' . $taxYear->year . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/tax-years/' . $taxYear->year) }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to {{ $taxYear->year }}</a>
        <h1 class="text-2xl font-bold mt-2">Import CSV into {{ $taxYear->year }}</h1>
    </div>

    <div class="bg-white border border-stone-200 rounded-lg p-6 max-w-xl">
        <form action="{{ url('/tax-years/' . $taxYear->year . '/import') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="mb-6">
                <label for="csv_file" class="block text-sm font-medium mb-2">CSV File</label>
                <input
                    type="file"
                    name="csv_file"
                    id="csv_file"
                    accept=".csv,.txt"
                    required
                    class="block w-full text-sm text-stone-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-stone-100 file:text-stone-700 hover:file:bg-stone-200"
                >
                @error('csv_file')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="csv_template_id" class="block text-sm font-medium mb-2">Column Template (optional)</label>
                <select
                    name="csv_template_id"
                    id="csv_template_id"
                    class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400"
                >
                    <option value="">Auto-detect columns</option>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-stone-400 mt-1">Select a saved template or auto-detect columns from the CSV header row.</p>
            </div>

            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Upload &amp; Preview
            </button>
        </form>
    </div>
@endsection