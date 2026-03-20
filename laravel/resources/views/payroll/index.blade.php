@extends('layouts.app')

@section('title', 'Payroll - EzTaxes')

@section('content')
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <h1 class="text-2xl font-bold">Payroll</h1>
    </div>

    @if($taxYears->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No tax years yet.</p>
            <p class="text-sm mt-2">Create a tax year first, then import Gusto payroll data.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach($taxYears as $taxYear)
                @php $s = $summaries[$taxYear->year]; @endphp
                <div class="bg-white border border-stone-200 rounded-lg p-5">
                    <div class="flex items-center justify-between mb-3">
                        <a href="{{ url('/payroll/' . $taxYear->year) }}" class="text-lg font-bold hover:underline">{{ $taxYear->year }}</a>
                        @if($s['has_data'])
                            <span class="text-xs bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded">Data imported</span>
                        @else
                            <span class="text-xs bg-stone-100 text-stone-500 px-2 py-0.5 rounded">No data</span>
                        @endif
                    </div>

                    @if($s['has_data'])
                        <a href="{{ url('/payroll/' . $taxYear->year) }}" class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm hover:bg-stone-50 -mx-2 px-2 py-2 rounded transition-colors">
                            <div>
                                <p class="text-stone-500">Officers</p>
                                <p class="font-medium">${{ number_format($s['officer_gross'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-stone-500">Employees</p>
                                <p class="font-medium">${{ number_format($s['employee_gross'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-stone-500">Employer Taxes</p>
                                <p class="font-medium">${{ number_format($s['employer_taxes'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-stone-500">Contractors</p>
                                <p class="font-medium">${{ number_format($s['total_contractors'], 2) }}</p>
                            </div>
                        </a>
                    @else
                        <p class="text-sm text-stone-400">Import Gusto CSV files from the <a href="{{ url('/import') }}" class="underline hover:text-stone-600">import page</a>.</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection