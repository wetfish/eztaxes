<?php

namespace App\Http\Controllers;

use App\Models\TaxYear;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request, int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();
        $filter = $request->input('filter');

        $query = Transaction::where('tax_year_id', $taxYear->id)
            ->with('buckets')
            ->orderBy('date', 'desc');

        if ($filter === 'unmatched') {
            $query->where('match_type', 'unmatched');
        } elseif ($filter === 'matched') {
            $query->where('match_type', '!=', 'unmatched');
        }

        $transactions = $query->paginate(50)->withQueryString();

        $counts = [
            'total' => Transaction::where('tax_year_id', $taxYear->id)->count(),
            'matched' => Transaction::where('tax_year_id', $taxYear->id)->where('match_type', '!=', 'unmatched')->count(),
            'unmatched' => Transaction::where('tax_year_id', $taxYear->id)->where('match_type', 'unmatched')->count(),
        ];

        return view('transactions.index', compact('taxYear', 'transactions', 'filter', 'counts'));
    }
}