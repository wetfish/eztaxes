<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\TaxYear;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TaxYearController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2099|unique:tax_years,year',
        ]);

        TaxYear::create([
            'year' => $request->year,
            'filing_status' => 'draft',
        ]);

        return redirect('/tax-years/' . $request->year)->with('success', "Tax year {$request->year} created.");
    }

    public function show(int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $buckets = Bucket::whereHas('transactions', function ($query) use ($taxYear) {
            $query->where('tax_year_id', $taxYear->id);
        })
            ->with(['transactions' => function ($query) use ($taxYear) {
                $query->where('tax_year_id', $taxYear->id);
            }])
            ->orderBy('sort_order')
            ->get();

        $imports = $taxYear->imports()->orderBy('imported_at', 'desc')->get();

        $unmatchedCount = Transaction::where('tax_year_id', $taxYear->id)
            ->where('match_type', 'unmatched')
            ->count();

        return view('tax-years.show', compact('taxYear', 'buckets', 'imports', 'unmatchedCount'));
    }
}