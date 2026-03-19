@extends('layouts.app')

@section('title', 'Import CSV - EzTaxes')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold">Import CSV</h1>
        <p class="text-sm text-stone-500 mt-1">Upload any CSV file. The format and tax year will be detected automatically.</p>
    </div>

    <div class="bg-white border border-stone-200 rounded-lg p-6 max-w-xl">
        <form action="{{ url('/import') }}" method="POST" enctype="multipart/form-data">
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

            @if($templates->isNotEmpty())
                <div class="mb-6">
                    <label for="csv_template_id" class="block text-sm font-medium mb-2">Override template (optional)</label>
                    <select
                        name="csv_template_id"
                        id="csv_template_id"
                        class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400"
                    >
                        <option value="">Auto-detect</option>
                        @foreach($templates as $template)
                            <option value="{{ $template->id }}">{{ $template->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-stone-400 mt-1">Only needed if auto-detection picks the wrong columns.</p>
                </div>
            @endif

            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Upload &amp; Preview
            </button>
        </form>
    </div>

    {{-- Supported Formats --}}
    <div class="mt-8 max-w-xl">
        <h2 class="text-sm font-medium text-stone-600 mb-3">Supported formats</h2>
        <div class="grid gap-2 text-sm text-stone-500">
            <p>Bank statements (most CSV formats with date, amount, description columns)</p>
            <p>Gusto Employee Payroll (custom report with pay date)</p>
            <p>Gusto US Contractor Payments</p>
            <p>Gusto International Contractor Payments</p>
        </div>
    </div>
@endsection