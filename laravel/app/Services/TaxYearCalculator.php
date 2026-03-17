<?php

namespace App\Services;

use App\Models\TaxYear;
use App\Models\Transaction;

class TaxYearCalculator
{
    /**
     * Recalculate the cached total_income and total_expenses on a TaxYear
     * from its distinct transactions that belong to normal-behavior buckets.
     */
    public function recalculate(TaxYear $taxYear): void
    {
        $totalIncome = Transaction::where('tax_year_id', $taxYear->id)
            ->where('amount', '>', 0)
            ->whereHas('buckets', function ($query) {
                $query->where('behavior', 'normal');
            })
            ->sum('amount');

        $totalExpenses = Transaction::where('tax_year_id', $taxYear->id)
            ->where('amount', '<', 0)
            ->whereHas('buckets', function ($query) {
                $query->where('behavior', 'normal');
            })
            ->sum('amount');

        $taxYear->update([
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
        ]);
    }
}