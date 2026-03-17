<?php

namespace App\Console\Commands;

use App\Models\TaxYear;
use App\Services\TaxYearCalculator;
use Illuminate\Console\Command;

class RecalculateTotals extends Command
{
    protected $signature = 'taxyear:recalculate {year?}';

    protected $description = 'Recalculate cached income and expense totals for a tax year (or all years)';

    public function handle(TaxYearCalculator $calculator): int
    {
        $year = $this->argument('year');

        if ($year) {
            $taxYear = TaxYear::where('year', $year)->first();

            if (!$taxYear) {
                $this->error("Tax year {$year} not found.");
                return Command::FAILURE;
            }

            $calculator->recalculate($taxYear);
            $this->info("{$year}: Income {$taxYear->total_income} | Expenses {$taxYear->total_expenses}");
        } else {
            $taxYears = TaxYear::all();

            foreach ($taxYears as $taxYear) {
                $calculator->recalculate($taxYear);
                $this->line("{$taxYear->year}: Income {$taxYear->total_income} | Expenses {$taxYear->total_expenses}");
            }

            $this->info('All tax years recalculated.');
        }

        return Command::SUCCESS;
    }
}