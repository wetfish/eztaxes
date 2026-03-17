<?php

namespace App\Http\Controllers;

use App\Models\Bucket;
use App\Models\BucketPattern;
use App\Models\TaxYear;
use App\Models\Transaction;
use App\Services\TaxYearCalculator;
use App\Services\TransactionMatcher;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request, int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();
        $filter = $request->input('filter');
        $testPattern = $request->input('pattern');

        $query = Transaction::where('tax_year_id', $taxYear->id)
            ->with('buckets')
            ->orderBy('date', 'desc');

        if ($filter === 'unmatched') {
            $query->where('match_type', 'unmatched');
        } elseif ($filter === 'matched') {
            $query->where('match_type', '!=', 'unmatched');
        }

        // If testing a pattern, filter to only matching descriptions
        $patternMatchCount = null;

        if ($testPattern && @preg_match('/' . $testPattern . '/i', '') !== false) {
            // Get all transaction IDs that match the pattern for this filter set
            $baseQuery = Transaction::where('tax_year_id', $taxYear->id);

            if ($filter === 'unmatched') {
                $baseQuery->where('match_type', 'unmatched');
            } elseif ($filter === 'matched') {
                $baseQuery->where('match_type', '!=', 'unmatched');
            }

            $allDescriptions = $baseQuery->pluck('description', 'id');
            $matchingIds = [];

            foreach ($allDescriptions as $id => $description) {
                if (@preg_match('/' . $testPattern . '/i', $description)) {
                    $matchingIds[] = $id;
                }
            }

            $patternMatchCount = count($matchingIds);
            $query->whereIn('id', $matchingIds);
        }

        $transactions = $query->paginate(50)->withQueryString();

        $counts = [
            'total' => Transaction::where('tax_year_id', $taxYear->id)->count(),
            'matched' => Transaction::where('tax_year_id', $taxYear->id)->where('match_type', '!=', 'unmatched')->count(),
            'unmatched' => Transaction::where('tax_year_id', $taxYear->id)->where('match_type', 'unmatched')->count(),
        ];

        $buckets = Bucket::where('is_active', true)->orderBy('name')->get();

        return view('transactions.index', compact(
            'taxYear', 'transactions', 'filter', 'counts',
            'buckets', 'testPattern', 'patternMatchCount'
        ));
    }

    /**
     * Manually assign a bucket to a single transaction.
     */
    public function assignBucket(Request $request, int $id, TaxYearCalculator $calculator)
    {
        $transaction = Transaction::findOrFail($id);

        $request->validate([
            'bucket_id' => 'required|exists:buckets,id',
        ]);

        if (!$transaction->buckets()->where('bucket_id', $request->bucket_id)->exists()) {
            $transaction->buckets()->attach($request->bucket_id, [
                'assigned_via' => 'manual',
                'bucket_pattern_id' => null,
            ]);
        }

        if ($transaction->match_type === 'unmatched') {
            $transaction->update(['match_type' => 'manual']);
        }

        $calculator->recalculate($transaction->taxYear);

        $bucket = Bucket::find($request->bucket_id);

        return back()->with('success', "Transaction assigned to '{$bucket->name}'.");
    }

    /**
     * Create a new pattern from the pattern builder and re-run matching.
     */
    public function createPattern(
        Request $request,
        TransactionMatcher $matcher,
        TaxYearCalculator $calculator
    ) {
        $request->validate([
            'bucket_id' => 'required|exists:buckets,id',
            'pattern' => 'required|string|max:500',
            'tax_year_id' => 'required|exists:tax_years,id',
        ]);

        if (@preg_match('/' . $request->pattern . '/i', '') === false) {
            return back()->with('error', "Invalid regex pattern: {$request->pattern}");
        }

        $bucket = Bucket::findOrFail($request->bucket_id);
        $taxYear = TaxYear::findOrFail($request->tax_year_id);

        $maxPriority = $bucket->patterns()->max('priority') ?? 0;

        BucketPattern::create([
            'bucket_id' => $bucket->id,
            'pattern' => $request->pattern,
            'priority' => $maxPriority + 1,
            'is_active' => true,
        ]);

        // Reload patterns and re-run matching on all unmatched transactions
        $matcher->loadPatterns();
        $matched = 0;

        $unmatched = Transaction::where('tax_year_id', $taxYear->id)
            ->where('match_type', 'unmatched')
            ->get();

        foreach ($unmatched as $unmatchedTx) {
            $matches = $matcher->match($unmatchedTx->description);

            if (!empty($matches)) {
                foreach ($matches as $match) {
                    if (!$unmatchedTx->buckets()->where('bucket_id', $match['bucket_id'])->exists()) {
                        $unmatchedTx->buckets()->attach($match['bucket_id'], [
                            'assigned_via' => 'pattern',
                            'bucket_pattern_id' => $match['bucket_pattern_id'],
                        ]);
                    }
                }

                $unmatchedTx->update(['match_type' => 'auto']);
                $matched++;
            }
        }

        $calculator->recalculate($taxYear);

        $message = "Pattern added to '{$bucket->name}'.";

        if ($matched > 0) {
            $message .= " {$matched} transaction" . ($matched > 1 ? 's' : '') . " matched.";
        }

        return redirect("/tax-years/{$taxYear->year}/transactions?filter=unmatched")
            ->with('success', $message);
    }
}