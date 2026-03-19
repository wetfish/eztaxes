@extends('layouts.app')

@section('title', 'Payroll ' . $taxYear->year . ' - EzTaxes')

@section('content')
    <div class="mb-8">
        <a href="{{ url('/payroll') }}" class="text-sm text-stone-500 hover:text-stone-700">&larr; Back to Payroll</a>
        <div class="flex items-center justify-between mt-2">
            <h1 class="text-2xl font-bold">{{ $taxYear->year }} Payroll</h1>
            <a href="{{ url('/import') }}" class="bg-stone-800 text-white px-4 py-2 rounded text-sm hover:bg-stone-700 transition-colors">
                Import CSV
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Officer Compensation</p>
            <p class="text-xl font-bold mt-1">${{ number_format($summary['officer_gross'], 2) }}</p>
            <p class="text-xs text-stone-400 mt-1">1120-S Line 7</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Employee Wages</p>
            <p class="text-xl font-bold mt-1">${{ number_format($summary['employee_gross'], 2) }}</p>
            <p class="text-xs text-stone-400 mt-1">1120-S Line 8</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Employer Taxes</p>
            <p class="text-xl font-bold mt-1">${{ number_format($summary['employer_taxes'], 2) }}</p>
            <p class="text-xs text-stone-400 mt-1">1120-S Line 12</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-lg p-4">
            <p class="text-xs text-stone-500 uppercase tracking-wide">Total Contractors</p>
            <p class="text-xl font-bold mt-1">${{ number_format($summary['total_contractors'], 2) }}</p>
            <p class="text-xs text-stone-400 mt-1">1120-S Line 19</p>
        </div>
    </div>

    {{-- Tax Line References --}}
    <div class="bg-white border border-stone-200 rounded-lg p-5 mb-8">
        <h2 class="font-bold mb-3">1120-S Tax Line References</h2>
        <div class="grid gap-2 text-sm">
            <div class="flex justify-between py-1 border-b border-stone-100">
                <span class="text-stone-600">Line 7 &mdash; Compensation of Officers</span>
                <span class="font-medium">${{ number_format($summary['officer_gross'], 2) }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-stone-100">
                <span class="text-stone-600">Line 8 &mdash; Salaries and Wages</span>
                <span class="font-medium">${{ number_format($summary['employee_gross'], 2) }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-stone-100">
                <span class="text-stone-600">Line 12 &mdash; Taxes and Licenses (Employer Taxes)</span>
                <span class="font-medium">${{ number_format($summary['employer_taxes'], 2) }}</span>
            </div>
            @if($summary['employer_contributions'] > 0)
                <div class="flex justify-between py-1 border-b border-stone-100">
                    <span class="text-stone-600">Line 17 &mdash; Pension/Profit-sharing (Employer Contributions)</span>
                    <span class="font-medium">${{ number_format($summary['employer_contributions'], 2) }}</span>
                </div>
            @endif
            <div class="flex justify-between py-1 border-b border-stone-100">
                <span class="text-stone-600">Line 19 &mdash; Other Deductions (Total Contractors)</span>
                <span class="font-medium">${{ number_format($summary['total_contractors'], 2) }}</span>
            </div>
            @if($summary['us_contractor_total'] > 0 && $summary['intl_contractor_total'] > 0)
                <div class="flex justify-between py-1 border-b border-stone-100 pl-6">
                    <span class="text-stone-400">US Contractors (1099-NEC required)</span>
                    <span class="text-stone-500">${{ number_format($summary['us_contractor_total'], 2) }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-stone-100 pl-6">
                    <span class="text-stone-400">International Contractors (no 1099)</span>
                    <span class="text-stone-500">${{ number_format($summary['intl_contractor_total'], 2) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Employee Payroll Section --}}
    @if($employeesByName->isNotEmpty())
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-8">
            <div class="px-5 py-4 bg-stone-50 border-b border-stone-200">
                <h2 class="font-bold">Employee Payroll</h2>
                <p class="text-xs text-stone-500 mt-0.5">{{ $employeeEntries->count() }} entries across {{ $employeesByName->count() }} employee(s)</p>
            </div>

            {{-- Per-employee summary with officer toggle --}}
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-2 font-medium">Employee</th>
                        <th class="px-4 py-2 font-medium">Role</th>
                        <th class="px-4 py-2 font-medium text-right">Gross Pay</th>
                        <th class="px-4 py-2 font-medium text-right">Employer Taxes</th>
                        <th class="px-4 py-2 font-medium text-right">Employer Cost</th>
                        <th class="px-4 py-2 font-medium text-right">Pay Periods</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($employeesByName as $emp)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $emp['name'] }}</td>
                            <td class="px-4 py-2">
                                <form action="{{ url('/payroll/' . $taxYear->year . '/toggle-officer') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="name" value="{{ $emp['name'] }}">
                                    @if($emp['is_officer'])
                                        <input type="hidden" name="is_officer" value="0">
                                        <button type="submit" class="text-xs bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded hover:bg-indigo-100 transition-colors" title="Click to change to Employee">
                                            Officer
                                        </button>
                                    @else
                                        <input type="hidden" name="is_officer" value="1">
                                        <button type="submit" class="text-xs bg-stone-100 text-stone-500 px-2 py-0.5 rounded hover:bg-stone-200 transition-colors" title="Click to change to Officer">
                                            Employee
                                        </button>
                                    @endif
                                </form>
                            </td>
                            <td class="px-4 py-2 text-right">${{ number_format($emp['gross_pay'], 2) }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($emp['employer_taxes'], 2) }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($emp['employer_cost'], 2) }}</td>
                            <td class="px-4 py-2 text-right text-stone-500">{{ $emp['entry_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-stone-50 font-bold">
                    <tr>
                        <td class="px-4 py-2" colspan="2">Total</td>
                        <td class="px-4 py-2 text-right">${{ number_format($summary['all_employee_gross'], 2) }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($summary['employer_taxes'], 2) }}</td>
                        <td class="px-4 py-2 text-right">${{ number_format($summary['employer_cost'], 2) }}</td>
                        <td class="px-4 py-2 text-right text-stone-500">{{ $employeeEntries->count() }}</td>
                    </tr>
                </tfoot>
            </table>

            {{-- Detailed entries (collapsible) --}}
            <details class="border-t border-stone-200">
                <summary class="px-5 py-3 text-sm text-stone-500 hover:text-stone-700 cursor-pointer bg-stone-50">
                    Show all {{ $employeeEntries->count() }} individual entries
                </summary>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-stone-100 text-left">
                            <tr>
                                <th class="px-4 py-2 font-medium">Date</th>
                                <th class="px-4 py-2 font-medium">Employee</th>
                                <th class="px-4 py-2 font-medium">Pay Period</th>
                                <th class="px-4 py-2 font-medium text-right">Gross</th>
                                <th class="px-4 py-2 font-medium text-right">Employee Taxes</th>
                                <th class="px-4 py-2 font-medium text-right">Employer Taxes</th>
                                <th class="px-4 py-2 font-medium text-right">Net Pay</th>
                                <th class="px-4 py-2 font-medium text-right">Employer Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach($employeeEntries->sortBy('date') as $entry)
                                <tr class="{{ $entry->gross_pay == 0 ? 'bg-amber-50' : '' }}">
                                    <td class="px-4 py-1.5">{{ $entry->date->format('m/d/Y') }}</td>
                                    <td class="px-4 py-1.5">
                                        {{ $entry->name }}
                                        @if($entry->is_officer)
                                            <span class="text-indigo-500 ml-1">&bull;</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-1.5 text-stone-500">{{ $entry->notes ?? '' }}</td>
                                    <td class="px-4 py-1.5 text-right">${{ number_format($entry->gross_pay, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right">${{ number_format($entry->employee_taxes, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right {{ $entry->employer_taxes < 0 ? 'text-red-600' : '' }}">${{ number_format($entry->employer_taxes, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right">${{ number_format($entry->net_pay, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right {{ $entry->employer_cost < 0 ? 'text-red-600' : '' }}">${{ number_format($entry->employer_cost, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endif

    {{-- US Contractors Section --}}
    @if($usContractors->isNotEmpty())
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-8">
            <div class="px-5 py-4 bg-stone-50 border-b border-stone-200">
                <h2 class="font-bold">US Contractors</h2>
                <p class="text-xs text-stone-500 mt-0.5">{{ $usContractors->count() }} contractor(s) &mdash; requires 1099-NEC for each</p>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-2 font-medium">Contractor</th>
                        <th class="px-4 py-2 font-medium">Department</th>
                        <th class="px-4 py-2 font-medium text-right">Total Paid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($usContractors as $entry)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $entry->name }}</td>
                            <td class="px-4 py-2 text-stone-500">{{ $entry->department ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($entry->gross_pay, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-stone-50 font-bold">
                    <tr>
                        <td class="px-4 py-2" colspan="2">Total</td>
                        <td class="px-4 py-2 text-right">${{ number_format($summary['us_contractor_total'], 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- International Contractors Section --}}
    @if($intlContractorsByName->isNotEmpty())
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden mb-8">
            <div class="px-5 py-4 bg-stone-50 border-b border-stone-200">
                <h2 class="font-bold">International Contractors</h2>
                <p class="text-xs text-stone-500 mt-0.5">{{ $intlContractorsByName->count() }} contractor(s) &mdash; no 1099-NEC required</p>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-2 font-medium">Contractor</th>
                        <th class="px-4 py-2 font-medium text-right">Total USD</th>
                        <th class="px-4 py-2 font-medium text-right">Payments</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($intlContractorsByName as $contractor)
                        <tr>
                            <td class="px-4 py-2 font-medium">{{ $contractor['name'] }}</td>
                            <td class="px-4 py-2 text-right">${{ number_format($contractor['total_usd'], 2) }}</td>
                            <td class="px-4 py-2 text-right text-stone-500">{{ $contractor['entry_count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-stone-50 font-bold">
                    <tr>
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right">${{ number_format($summary['intl_contractor_total'], 2) }}</td>
                        <td class="px-4 py-2 text-right text-stone-500">{{ $intlContractorEntries->count() }}</td>
                    </tr>
                </tfoot>
            </table>

            <details class="border-t border-stone-200">
                <summary class="px-5 py-3 text-sm text-stone-500 hover:text-stone-700 cursor-pointer bg-stone-50">
                    Show all {{ $intlContractorEntries->count() }} individual payments
                </summary>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-stone-100 text-left">
                            <tr>
                                <th class="px-4 py-2 font-medium">Date</th>
                                <th class="px-4 py-2 font-medium">Contractor</th>
                                <th class="px-4 py-2 font-medium">Wage Type</th>
                                <th class="px-4 py-2 font-medium text-right">USD Amount</th>
                                <th class="px-4 py-2 font-medium text-right">Hours</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach($intlContractorEntries->sortBy('date') as $entry)
                                <tr>
                                    <td class="px-4 py-1.5">{{ $entry->date->format('m/d/Y') }}</td>
                                    <td class="px-4 py-1.5">{{ $entry->name }}</td>
                                    <td class="px-4 py-1.5 text-stone-500">{{ $entry->wage_type ?? '—' }}</td>
                                    <td class="px-4 py-1.5 text-right">${{ number_format($entry->gross_pay, 2) }}</td>
                                    <td class="px-4 py-1.5 text-right">{{ $entry->hours ? number_format($entry->hours, 1) : '—' }}</td>
                                    <td class="px-4 py-1.5 text-stone-500">{{ $entry->payment_status ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endif

    {{-- No Data State --}}
    @if($employeesByName->isEmpty() && $usContractors->isEmpty() && $intlContractorsByName->isEmpty())
        <div class="text-center py-16 text-stone-400">
            <p class="text-lg">No payroll data for {{ $taxYear->year }}.</p>
            <p class="text-sm mt-2">
                <a href="{{ url('/import') }}" class="underline hover:text-stone-600">Import a Gusto CSV</a> to get started.
            </p>
        </div>
    @endif

    {{-- Import History --}}
    @if($imports->isNotEmpty())
        <div class="bg-white border border-stone-200 rounded-lg overflow-hidden">
            <div class="px-5 py-4 bg-stone-50 border-b border-stone-200">
                <h2 class="font-bold">Import History</h2>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-stone-100 text-left">
                    <tr>
                        <th class="px-4 py-2 font-medium">File</th>
                        <th class="px-4 py-2 font-medium">Type</th>
                        <th class="px-4 py-2 font-medium text-right">Rows</th>
                        <th class="px-4 py-2 font-medium">Imported</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                    @foreach($imports as $import)
                        <tr>
                            <td class="px-4 py-2">{{ $import->original_filename }}</td>
                            <td class="px-4 py-2">
                                @switch($import->type)
                                    @case('employee')
                                        <span class="text-xs bg-blue-50 text-blue-700 px-2 py-0.5 rounded">Employee</span>
                                        @break
                                    @case('us_contractor')
                                        <span class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded">US Contractor</span>
                                        @break
                                    @case('intl_contractor')
                                        <span class="text-xs bg-teal-50 text-teal-700 px-2 py-0.5 rounded">Intl Contractor</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="px-4 py-2 text-right">{{ $import->rows_total }}</td>
                            <td class="px-4 py-2 text-stone-500">{{ $import->imported_at->format('M j, Y g:ia') }}</td>
                            <td class="px-4 py-2 text-right">
                                <form action="{{ url('/payroll/imports/' . $import->id) }}" method="POST" onsubmit="return confirm('Delete this import and all its entries?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection