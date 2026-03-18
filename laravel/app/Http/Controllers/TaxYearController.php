<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\BucketGroup;
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

        // Load groups with their buckets' transactions for this year
        $groups = BucketGroup::with(['buckets' => function ($query) use ($taxYear) {
            $query->whereHas('transactions', function ($q) use ($taxYear) {
                $q->where('tax_year_id', $taxYear->id);
            })
            ->with(['transactions' => function ($q) use ($taxYear) {
                $q->where('tax_year_id', $taxYear->id);
            }]);
        }])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn($group) => $group->buckets->isNotEmpty());

        // Compute subtotals per group
        $groupBreakdown = [];

        foreach ($groups as $group) {
            $income = 0;
            $expenses = 0;

            foreach ($group->buckets as $bucket) {
                if ($bucket->behavior === 'normal') {
                    $income += $bucket->transactions->where('amount', '>', 0)->sum('amount');
                    $expenses += $bucket->transactions->where('amount', '<', 0)->sum('amount');
                }
            }

            $groupBreakdown[] = [
                'group' => $group,
                'income' => $income,
                'expenses' => $expenses,
                'net' => $income + $expenses,
            ];
        }

        // Unassigned buckets with transactions
        $unassignedBuckets = Bucket::whereNull('bucket_group_id')
            ->whereHas('transactions', function ($query) use ($taxYear) {
                $query->where('tax_year_id', $taxYear->id);
            })
            ->with(['transactions' => function ($query) use ($taxYear) {
                $query->where('tax_year_id', $taxYear->id);
            }])
            ->orderBy('sort_order')
            ->get();

        $unassignedIncome = 0;
        $unassignedExpenses = 0;

        foreach ($unassignedBuckets as $bucket) {
            if ($bucket->behavior === 'normal') {
                $unassignedIncome += $bucket->transactions->where('amount', '>', 0)->sum('amount');
                $unassignedExpenses += $bucket->transactions->where('amount', '<', 0)->sum('amount');
            }
        }

        $imports = $taxYear->imports()->orderBy('imported_at', 'desc')->get();

        $unmatchedCount = Transaction::where('tax_year_id', $taxYear->id)
            ->where('match_type', 'unmatched')
            ->count();

        return view('tax-years.show', compact(
            'taxYear', 'groups', 'groupBreakdown',
            'unassignedBuckets', 'unassignedIncome', 'unassignedExpenses',
            'imports', 'unmatchedCount'
        ));
    }
}