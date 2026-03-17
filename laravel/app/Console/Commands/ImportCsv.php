<?php

namespace App\Console\Commands;

use App\Models\CsvTemplate;
use App\Models\TaxYear;
use App\Services\CsvImporter;
use Illuminate\Console\Command;

class ImportCsv extends Command
{
    protected $signature = 'csv:import
                            {year : The tax year to import into}
                            {file : Path to the CSV file}
                            {--template= : Name of an existing CSV template to use}';

    protected $description = 'Import a CSV file into a tax year, matching transactions against bucket patterns';

    public function handle(CsvImporter $importer): int
    {
        $year = (int) $this->argument('year');
        $filepath = $this->argument('file');

        // Resolve file path — check as-is, then try legacy/ directory
        if (!file_exists($filepath)) {
            $legacyPath = '/var/www/legacy/' . $filepath;

            if (file_exists($legacyPath)) {
                $filepath = $legacyPath;
            } else {
                $this->error("File not found: {$filepath}");
                return Command::FAILURE;
            }
        }

        // Find or create the tax year
        $taxYear = TaxYear::where('year', $year)->first();

        if (!$taxYear) {
            $this->error("Tax year {$year} does not exist. Create it first with: php artisan taxyear:create {$year}");
            return Command::FAILURE;
        }

        // Parse the CSV
        $this->info("Parsing {$filepath}...");
        $rows = $importer->parseFile($filepath);

        if (empty($rows)) {
            $this->error('CSV file is empty or could not be parsed.');
            return Command::FAILURE;
        }

        $this->info('Found ' . count($rows) . ' rows (including header).');

        // Detect headers
        $headers = $importer->detectHeaders($rows);
        $this->newLine();
        $this->info('Detected columns:');

        foreach ($headers as $index => $header) {
            $this->line("  [{$index}] {$header}");
        }

        // Try to use an existing template
        $columnMapping = null;
        $csvTemplateId = null;
        $templateName = $this->option('template');

        if ($templateName) {
            $template = CsvTemplate::where('name', $templateName)->first();

            if ($template) {
                $mapping = $template->column_mapping;
                $dateHeader = $headers[$mapping['date']] ?? '?';
                $amountHeader = $headers[$mapping['amount']] ?? '?';
                $descHeader = $headers[$mapping['description']] ?? '?';

                $this->newLine();
                $this->info("Using template '{$templateName}':");
                $this->line("  Date column: [{$mapping['date']}] {$dateHeader}");
                $this->line("  Amount column: [{$mapping['amount']}] {$amountHeader}");
                $this->line("  Description column: [{$mapping['description']}] {$descHeader}");

                if ($this->confirm('Use this mapping?', true)) {
                    $columnMapping = $mapping;
                    $csvTemplateId = $template->id;
                }
            } else {
                $this->warn("Template '{$templateName}' not found.");
            }
        }

        // If no template used, ask user to map columns
        if (!$columnMapping) {
            $this->newLine();
            $columnMapping = [
                'date' => (int) $this->ask('Which column number contains the date?'),
                'amount' => (int) $this->ask('Which column number contains the amount?'),
                'description' => (int) $this->ask('Which column number contains the description?'),
            ];

            // Show a preview of the first few data rows with the selected mapping
            $this->newLine();
            $this->info('Preview (first 3 data rows):');
            $previewRows = array_slice($rows, 1, 3);

            foreach ($previewRows as $row) {
                $date = $row[$columnMapping['date']] ?? '(empty)';
                $amount = $row[$columnMapping['amount']] ?? '(empty)';
                $desc = $row[$columnMapping['description']] ?? '(empty)';
                $this->line("  {$date} | {$amount} | {$desc}");
            }

            if (!$this->confirm('Does this look correct?', true)) {
                $this->info('Aborted.');
                return Command::SUCCESS;
            }

            // Offer to save as a template
            if ($this->confirm('Save this column mapping as a reusable template?')) {
                $name = $this->ask('Template name (e.g., "Local Credit Union Checking")');

                $template = CsvTemplate::create([
                    'name' => $name,
                    'column_mapping' => $columnMapping,
                ]);

                $csvTemplateId = $template->id;
                $this->info("Template '{$name}' saved.");
            }
        }

        // Run the import
        $this->newLine();
        $this->info('Importing transactions...');

        $import = $importer->import(
            $taxYear,
            $rows,
            $columnMapping,
            basename($filepath),
            $csvTemplateId
        );

        // Display results
        $this->newLine();
        $this->info('Import complete:');
        $this->line("  Total rows:    {$import->rows_total}");
        $this->line("  Matched:       {$import->rows_matched}");
        $this->line("  Unmatched:     {$import->rows_unmatched}");
        $this->line("  Ignored:       {$import->rows_ignored}");

        return Command::SUCCESS;
    }
}