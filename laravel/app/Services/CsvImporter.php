<?php

namespace App\Services;

use App\Models\Bucket;
use App\Models\Import;
use App\Models\TaxYear;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CsvImporter
{
    private TransactionMatcher $matcher;
    private TaxYearCalculator $calculator;

    public function __construct(TransactionMatcher $matcher, TaxYearCalculator $calculator)
    {
        $this->matcher = $matcher;
        $this->calculator = $calculator;
    }

    /**
     * Parse a CSV file into an array of rows.
     * Returns the raw rows as arrays.
     *
     * @return array<int, array<int, string>>
     */
    public function parseFile(string $filepath): array
    {
        $contents = file_get_contents($filepath);
        $lines = preg_split('/\R/m', $contents);

        return array_filter(
            array_map('str_getcsv', $lines),
            fn($row) => !empty(array_filter($row, fn($cell) => $cell !== null && $cell !== ''))
        );
    }

    /**
     * Detect potential header row from parsed CSV data.
     * Returns the first row of the CSV as a candidate header.
     *
     * @return array<int, string>
     */
    public function detectHeaders(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        return array_map('trim', $rows[0]);
    }

    /**
     * Import transactions from parsed CSV rows into a tax year.
     *
     * @param TaxYear $taxYear
     * @param array $rows Parsed CSV rows (including header row)
     * @param array $columnMapping Maps internal names to CSV column indices: ['date' => 0, 'amount' => 2, 'description' => 1]
     * @param string $filename Original filename for the import record
     * @param int|null $csvTemplateId Optional CSV template ID
     * @return Import The created import record with final counts
     */
    public function import(
        TaxYear $taxYear,
        array $rows,
        array $columnMapping,
        string $filename,
        ?int $csvTemplateId = null
    ): Import {
        // Create the import record
        $import = Import::create([
            'tax_year_id' => $taxYear->id,
            'csv_template_id' => $csvTemplateId,
            'original_filename' => $filename,
            'imported_at' => now(),
        ]);

        // Skip the header row
        $dataRows = array_slice($rows, 1);

        $rowsTotal = 0;
        $rowsMatched = 0;
        $rowsUnmatched = 0;
        $rowsIgnored = 0;

        // Cache ignored bucket IDs for quick lookup
        $ignoredBucketIds = Bucket::where('behavior', 'ignored')
            ->pluck('id')
            ->toArray();

        DB::beginTransaction();

        try {
            foreach ($dataRows as $row) {
                // Extract mapped fields
                $date = $row[$columnMapping['date']] ?? null;
                $description = $row[$columnMapping['description']] ?? null;
                $amount = $row[$columnMapping['amount']] ?? null;

                // Skip rows with missing required data
                if (empty($date) || empty($description) || $amount === null || $amount === '') {
                    continue;
                }

                // Clean the amount — remove currency symbols, commas, whitespace
                $cleanAmount = preg_replace('/[^0-9.\-]/', '', $amount);

                if (!is_numeric($cleanAmount)) {
                    continue;
                }

                $rowsTotal++;

                // Parse the date — try common formats
                $parsedDate = $this->parseDate($date);

                if (!$parsedDate) {
                    continue;
                }

                // Run the matcher
                $matches = $this->matcher->match($description);

                // Determine match type
                $matchType = 'unmatched';
                $isIgnored = false;

                if (!empty($matches)) {
                    $matchType = 'auto';

                    // Check if all matches are ignored buckets
                    $matchedBucketIds = array_column($matches, 'bucket_id');
                    $isIgnored = empty(array_diff($matchedBucketIds, $ignoredBucketIds));
                }

                // Create the transaction
                $transaction = Transaction::create([
                    'tax_year_id' => $taxYear->id,
                    'import_id' => $import->id,
                    'date' => $parsedDate,
                    'description' => $description,
                    'amount' => $cleanAmount,
                    'match_type' => $matchType,
                ]);

                // Create pivot entries for all matched buckets
                foreach ($matches as $match) {
                    $transaction->buckets()->attach($match['bucket_id'], [
                        'assigned_via' => 'pattern',
                        'bucket_pattern_id' => $match['bucket_pattern_id'],
                    ]);
                }

                // Update counters
                if ($matchType === 'unmatched') {
                    $rowsUnmatched++;
                } elseif ($isIgnored) {
                    $rowsIgnored++;
                } else {
                    $rowsMatched++;
                }
            }

            // Update the import record with counts
            $import->update([
                'rows_total' => $rowsTotal,
                'rows_matched' => $rowsMatched,
                'rows_unmatched' => $rowsUnmatched,
                'rows_ignored' => $rowsIgnored,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $import->delete();
            throw $e;
        }

        // Recalculate cached totals on the tax year
        $this->calculator->recalculate($taxYear);

        return $import;
    }

    /**
     * Attempt to parse a date string in common formats.
     */
    private function parseDate(string $date): ?string
    {
        $formats = [
            'n/j/Y',     // 1/5/2024
            'n/j/y',     // 1/5/24
            'm/d/Y',     // 01/05/2024
            'm/d/y',     // 01/05/24
            'Y-m-d',     // 2024-01-05
            'M d, Y',    // Jan 05, 2024
            'M j, Y',    // Jan 5, 2024
            'F d, Y',    // January 05, 2024
            'F j, Y',    // January 5, 2024
            'd-M-Y',     // 05-Jan-2024
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, trim($date));

            if ($parsed && $parsed->format($format) === trim($date)) {
                return $parsed->format('Y-m-d');
            }
        }

        // Last resort — let PHP try to figure it out
        try {
            $parsed = new \DateTime(trim($date));
            return $parsed->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}