<?php

namespace App\Console\Commands;

use App\Models\Bucket;
use App\Models\TaxYear;
use App\Models\Transaction;
use Illuminate\Console\Command;

class Report extends Command
{
    protected $signature = 'report
                            {year : The tax year to report on}
                            {--unmatched : Show unmatched transactions}
                            {--bucket= : Show only a specific bucket by slug}
                            {--all : Show individual transactions for all buckets}';

    protected $description = 'Generate a tax year report with per-bucket income and expenses';

    public function handle(): int
    {
        $year = (int) $this->argument('year');
        $taxYear = TaxYear::where('year', $year)->first();

        if (!$taxYear) {
            $this->error("Tax year {$year} not found.");
            return Command::FAILURE;
        }

        // Single bucket detail view
        $bucketSlugFilter = $this->option('bucket');

        if ($bucketSlugFilter) {
            return $this->showBucketDetail($taxYear, $bucketSlugFilter);
        }

        // Full report
        return $this->showFullReport($taxYear);
    }

    private function showBucketDetail(TaxYear $taxYear, string $slug): int
    {
        $bucket = Bucket::where('slug', $slug)->first();

        if (!$bucket) {
            $this->error("Bucket '{$slug}' not found.");
            return Command::FAILURE;
        }

        $transactions = $bucket->transactions()
            ->where('tax_year_id', $taxYear->id)
            ->orderBy('date')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info("No transactions for bucket '{$bucket->name}' in {$taxYear->year}.");
            return Command::SUCCESS;
        }

        $income = $transactions->where('amount', '>', 0)->sum('amount');
        $expenses = $transactions->where('amount', '<', 0)->sum('amount');

        $this->info("=== {$bucket->name} ({$taxYear->year}) ===");

        if ($bucket->behavior !== 'normal') {
            $this->warn("Behavior: {$bucket->behavior}");
        }

        $this->newLine();

        foreach ($transactions as $transaction) {
            $this->line("  {$transaction->date->format('m/d/Y')} | {$transaction->amount} | {$transaction->description}");
        }

        $this->newLine();
        $this->line("Transactions: {$transactions->count()}");
        $this->line("Income:       {$income}");
        $this->line("Expenses:     {$expenses}");

        return Command::SUCCESS;
    }

    private function showFullReport(TaxYear $taxYear): int
    {
        $showAll = $this->option('all');

        $buckets = Bucket::whereHas('transactions', function ($query) use ($taxYear) {
            $query->where('tax_year_id', $taxYear->id);
        })->orderBy('sort_order')->get();

        $this->info("=== Tax Year {$taxYear->year} Report ===");
        $this->newLine();

        foreach ($buckets as $bucket) {
            $transactions = $bucket->transactions()
                ->where('tax_year_id', $taxYear->id)
                ->get();

            $income = $transactions->where('amount', '>', 0)->sum('amount');
            $expenses = $transactions->where('amount', '<', 0)->sum('amount');

            $label = $bucket->name;

            if ($bucket->behavior !== 'normal') {
                $label .= " [{$bucket->behavior}]";
            }

            $this->line("{$label} - Income: {$income} | Expenses: {$expenses}");

            if ($showAll) {
                foreach ($transactions as $transaction) {
                    $this->line("    {$transaction->date->format('m/d/Y')} | {$transaction->amount} | {$transaction->description}");
                }
                $this->newLine();
            }
        }

        // Grand totals
        $this->newLine();
        $this->info('=== Grand Totals (distinct transactions, normal buckets only) ===');

        $distinctIncome = Transaction::where('tax_year_id', $taxYear->id)
            ->where('amount', '>', 0)
            ->whereHas('buckets', function ($query) {
                $query->where('behavior', 'normal');
            })
            ->sum('amount');

        $distinctExpenses = Transaction::where('tax_year_id', $taxYear->id)
            ->where('amount', '<', 0)
            ->whereHas('buckets', function ($query) {
                $query->where('behavior', 'normal');
            })
            ->sum('amount');

        $this->line("Total Income:   {$distinctIncome}");
        $this->line("Total Expenses: {$distinctExpenses}");
        $this->line("Net:            " . ($distinctIncome + $distinctExpenses));

        // Unmatched transactions
        $unmatched = Transaction::where('tax_year_id', $taxYear->id)
            ->where('match_type', 'unmatched')
            ->orderBy('date')
            ->get();

        $this->newLine();
        $this->info("Unmatched transactions: {$unmatched->count()}");

        if ($this->option('unmatched') && $unmatched->isNotEmpty()) {
            $unmatchedIncome = $unmatched->where('amount', '>', 0)->sum('amount');
            $unmatchedExpenses = $unmatched->where('amount', '<', 0)->sum('amount');

            $this->line("Unmatched Income:   {$unmatchedIncome}");
            $this->line("Unmatched Expenses: {$unmatchedExpenses}");
            $this->newLine();

            foreach ($unmatched as $transaction) {
                $this->line("  {$transaction->date->format('m/d/Y')} | {$transaction->amount} | {$transaction->description}");
            }
        }

        return Command::SUCCESS;
    }
}