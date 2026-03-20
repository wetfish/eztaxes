@extends('layouts.app')

@section('title', 'Tax Years - EzTaxes')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <h1 class="text-2xl font-bold">Tax Years</h1>
        <form action="{{ url('/tax-years') }}" method="POST" class="flex items-center gap-3">
            @csrf
            <input
                type="number"
                name="year"
                placeholder="e.g. 2025"
                min="2000"
                max="2099"
                required
                class="border border-stone-300 rounded px-3 py-2 text-sm w-32 focus:outline-none focus:ring-2 focus:ring-stone-400"
            >
            <button type="submit" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                New Tax Year
            </button>
        </form>
    </div>

    @if($taxYears->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No tax years yet.</p>
            <p class="text-sm mt-2">Create one to get started.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($taxYears as $taxYear)
                <a href="{{ url('/tax-years/' . $taxYear->year) }}" class="block bg-white border border-stone-200 rounded-lg p-6 hover:border-stone-400 transition-colors">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold">{{ $taxYear->year }}</h2>
                            <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded
                                {{ $taxYear->filing_status === 'filed' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ $taxYear->filing_status === 'draft' ? 'bg-amber-100 text-amber-700' : '' }}
                                {{ $taxYear->filing_status === 'amended' ? 'bg-blue-100 text-blue-700' : '' }}
                            ">{{ ucfirst($taxYear->filing_status) }}</span>
                        </div>
                        <div class="text-right text-sm">
                            <div class="text-emerald-600">Income: ${{ number_format($taxYear->total_income, 2) }}</div>
                            <div class="text-red-600">Expenses: ${{ number_format(abs($taxYear->total_expenses), 2) }}</div>
                            <div class="text-stone-600 font-medium mt-1">Net: ${{ number_format($taxYear->total_income + $taxYear->total_expenses, 2) }}</div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection