<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollImport;
use App\Models\TaxYear;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function index()
    {
        $taxYears = TaxYear::orderByDesc('year')->get();

        $summaries = [];

        foreach ($taxYears as $taxYear) {
            $entries = PayrollEntry::where('tax_year_id', $taxYear->id);

            $employeeEntries = (clone $entries)->where('type', 'employee');
            $officerEntries = (clone $employeeEntries)->where('is_officer', true);
            $nonOfficerEntries = (clone $employeeEntries)->where('is_officer', false);
            $usContractorEntries = (clone $entries)->where('type', 'us_contractor');
            $intlContractorEntries = (clone $entries)->where('type', 'intl_contractor');

            $usTotal = (clone $usContractorEntries)->sum('gross_pay');
            $intlTotal = (clone $intlContractorEntries)->sum('gross_pay');

            $summaries[$taxYear->year] = [
                'officer_gross' => (clone $officerEntries)->sum('gross_pay'),
                'employee_gross' => (clone $nonOfficerEntries)->sum('gross_pay'),
                'employer_taxes' => (clone $employeeEntries)->sum('employer_taxes'),
                'total_contractors' => $usTotal + $intlTotal,
                'has_data' => (clone $entries)->exists(),
            ];
        }

        return view('payroll.index', compact('taxYears', 'summaries'));
    }

    public function show(int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $entries = PayrollEntry::where('tax_year_id', $taxYear->id)
            ->orderBy('date')
            ->get();

        $imports = PayrollImport::where('tax_year_id', $taxYear->id)
            ->orderByDesc('imported_at')
            ->get();

        // Split employee entries by officer status
        $employeeEntries = $entries->where('type', 'employee');
        $officerEntries = $employeeEntries->where('is_officer', true);
        $nonOfficerEntries = $employeeEntries->where('is_officer', false);
        $usContractorEntries = $entries->where('type', 'us_contractor');
        $intlContractorEntries = $entries->where('type', 'intl_contractor');

        $summary = [
            'officer_gross' => $officerEntries->sum('gross_pay'),
            'employee_gross' => $nonOfficerEntries->sum('gross_pay'),
            'all_employee_gross' => $employeeEntries->sum('gross_pay'),
            'employee_deductions' => $employeeEntries->sum('employee_deductions'),
            'employer_contributions' => $employeeEntries->sum('employer_contributions'),
            'employee_taxes' => $employeeEntries->sum('employee_taxes'),
            'employer_taxes' => $employeeEntries->sum('employer_taxes'),
            'net_pay' => $employeeEntries->sum('net_pay'),
            'employer_cost' => $employeeEntries->sum('employer_cost'),
            'check_amount' => $employeeEntries->sum('check_amount'),
            'us_contractor_total' => $usContractorEntries->sum('gross_pay'),
            'intl_contractor_total' => $intlContractorEntries->sum('gross_pay'),
        ];

        $summary['total_contractors'] = $summary['us_contractor_total']
            + $summary['intl_contractor_total'];

        // Group employee entries by name for per-person summaries
        $employeesByName = $employeeEntries->groupBy('name')->map(function ($group, $name) {
            return [
                'name' => $name,
                'is_officer' => $group->first()->is_officer,
                'gross_pay' => $group->sum('gross_pay'),
                'employer_taxes' => $group->sum('employer_taxes'),
                'employer_cost' => $group->sum('employer_cost'),
                'entry_count' => $group->count(),
            ];
        })->sortBy('name');

        // US contractors
        $usContractors = $usContractorEntries->sortBy('name');

        // Intl contractors grouped by name
        $intlContractorsByName = $intlContractorEntries->groupBy('name')->map(function ($group, $name) {
            return [
                'name' => $name,
                'total_usd' => $group->sum('gross_pay'),
                'entry_count' => $group->count(),
            ];
        })->sortBy('name');

        return view('payroll.show', compact(
            'taxYear', 'summary', 'imports',
            'employeesByName', 'usContractors', 'intlContractorsByName',
            'employeeEntries', 'usContractorEntries', 'intlContractorEntries'
        ));
    }

    /**
     * Toggle officer status for all entries matching a given employee name.
     */
    public function toggleOfficer(Request $request, int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $request->validate([
            'name' => 'required|string',
            'is_officer' => 'required|in:0,1',
        ]);

        PayrollEntry::where('tax_year_id', $taxYear->id)
            ->where('type', 'employee')
            ->where('name', $request->input('name'))
            ->update(['is_officer' => (bool) $request->input('is_officer')]);

        $label = $request->input('is_officer') ? 'marked as officer' : 'marked as employee';

        return redirect("/payroll/{$year}")->with('success', "{$request->input('name')} {$label}.");
    }

    public function destroyImport(int $id)
    {
        $import = PayrollImport::findOrFail($id);
        $year = $import->taxYear->year;

        $import->delete();

        return redirect("/payroll/{$year}")->with('success', 'Payroll import and all associated entries deleted.');
    }
}