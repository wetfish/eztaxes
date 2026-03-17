@extends('layouts.app')

@section('title', 'Confirm Import - ' . $taxYear->year . ' - eztaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/tax-years/' . $taxYear->year . '/import') }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to upload</a>
        <h1 class="text-2xl font-bold mt-2">Confirm Column Mapping</h1>
        <p class="text-sm text-stone-500 mt-1">{{ $filename }} &mdash; {{ $rowCount }} data rows detected</p>
    </div>

    <form action="{{ url('/tax-years/' . $taxYear->year . '/import/process') }}" method="POST">
        @csrf
        <input type="hidden" name="stored_file" value="{{ $storedFile }}">
        <input type="hidden" name="filename" value="{{ $filename }}">

        {{-- Column Mapping --}}
        <div class="bg-white border border-stone-200 rounded-lg p-6 mb-6 max-w-xl">
            <h2 class="font-medium mb-4">Map CSV columns to fields</h2>

            <div class="grid gap-4">
                <div>
                    <label for="col_date" class="block text-sm font-medium mb-1">Date column</label>
                    <select name="col_date" id="col_date" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                        @foreach($headers as $index => $header)
                            <option value="{{ $index }}" {{ $mappedColumns['date'] === $index ? 'selected' : '' }}>
                                [{{ $index }}] {{ $header }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="col_amount" class="block text-sm font-medium mb-1">Amount column</label>
                    <select name="col_amount" id="col_amount" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                        @foreach($headers as $index => $header)
                            <option value="{{ $index }}" {{ $mappedColumns['amount'] === $index ? 'selected' : '' }}>
                                [{{ $index }}] {{ $header }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="col_description" class="block text-sm font-medium mb-1">Description column</label>
                    <select name="col_description" id="col_description" class="border border-stone-300 rounded px-3 py-2 text-sm w-full focus:outline-none focus:ring-2 focus:ring-stone-400" required>
                        @foreach($headers as $index => $header)
                            <option value="{{ $index }}" {{ $mappedColumns['description'] === $index ? 'selected' : '' }}>
                                [{{ $index }}] {{ $header }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Preview --}}
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-6">
            <h2 class="font-medium px-4 py-3 bg-stone-50 border-b border-stone-200">Preview (first {{ count($preview) }} rows)</h2>
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        @foreach($headers as $header)
                            <th class="px-4 py-2 font-medium">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($preview as $row)
                        <tr>
                            @foreach($headers as $index => $header)
                                <td class="px-4 py-2">{{ $row[$index] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Save Template Option --}}
        <div class="bg-white border border-stone-200 rounded-lg p-6 mb-6 max-w-xl">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="save_template" value="1" class="rounded border-stone-300">
                Save this column mapping as a reusable template
            </label>
            <input
                type="text"
                name="template_name"
                placeholder="Template name, e.g. Local Credit Union Checking"
                class="border border-stone-300 rounded px-3 py-2 text-sm w-full mt-3 focus:outline-none focus:ring-2 focus:ring-stone-400"
            >
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="bg-stone-800 text-white px-6 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Import Transactions
            </button>
            <a href="{{ url('/tax-years/' . $taxYear->year . '/import') }}" class="text-sm text-stone-500 hover:text-stone-700">Cancel</a>
        </div>
    </form>
@endsection