<?php

namespace App\Console\Commands;

use App\Models\TaxYear;
use Illuminate\Console\Command;

class CreateTaxYear extends Command
{
    protected $signature = 'taxyear:create {year}';

    protected $description = 'Create a new tax year record';

    public function handle(): int
    {
        $year = (int) $this->argument('year');

        if ($year < 2000 || $year > 2099) {
            $this->error("Invalid year: {$year}");
            return Command::FAILURE;
        }

        if (TaxYear::where('year', $year)->exists()) {
            $this->warn("Tax year {$year} already exists.");
            return Command::SUCCESS;
        }

        TaxYear::create([
            'year' => $year,
            'filing_status' => 'draft',
        ]);

        $this->info("Tax year {$year} created.");

        return Command::SUCCESS;
    }
}