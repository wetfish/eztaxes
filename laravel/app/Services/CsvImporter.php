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
     * Detects delimiter (comma vs tab) automatically.
     *
     * @return array<int, array<int, string>>
     */
    public function parseFile(string $filepath): array
    {
        $contents = file_get_contents($filepath);
        $lines = preg_split('/\R/m', $contents);

        // Detect delimiter
        $tabCount = substr_count($contents, "\t");
        $commaCount = substr_count($contents, ",");
        $delimiter = $tabCount > $commaCount ? "\t" : ",";

        return array_values(array_filter(
            array_map(fn($line) => str_getcsv($line, $delimiter), $lines),
            fn($row) => !empty(array_filter($row, fn($cell) => $cell !== null && $cell !== ''))
        ));
    }

    /**
     * Find the header row in parsed CSV data by scanning for known column names.
     * Returns the index of the header row, or 0 if no known headers are found.
     */
    public function findHeaderRow(array $rows): int
    {
        $knownHeaders = [
            // Bank CSV headers
            'date', 'posting date', 'trans date', 'transaction date',
            'amount', 'debit', 'credit', 'transaction amount',
            'description', 'memo', 'transaction description', 'details', 'narrative',
            // Gusto employee headers
            'gross earnings', 'net pay', 'check amount', 'payroll pay date',
            'total employee taxes', 'total employer taxes', 'total employer cost',
            // Gusto US contractor headers
            'last name', 'first name', 'total amount',
            // Gusto international contractor headers
            'contractor name', 'processing date', 'usd amount', 'wage type',
        ];

        foreach ($rows as $index => $row) {
            $lowered = array_map(fn($cell) => strtolower(trim($cell ?? '')), $row);
            $matches = count(array_intersect($knownHeaders, $lowered));

            if ($matches >= 2) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Detect headers from parsed CSV data, scanning past preamble rows.
     *
     * @return array{headers: array<int, string>, headerRowIndex: int}
     */
    public function detectHeaders(array $rows): array
    {
        if (empty($rows)) {
            return ['headers' => [], 'headerRowIndex' => 0];
        }

        $headerRowIndex = $this->findHeaderRow($rows);

        return [
            'headers' => array_map('trim', $rows[$headerRowIndex]),
            'headerRowIndex' => $headerRowIndex,
        ];
    }

    /**
     * Detect the tax year from CSV data.
     * Tries preamble rows first (Gusto "Date Range" row), then scans data rows for dates.
     */
    public function detectYear(array $rows, int $headerRowIndex, array $columnMapping = []): ?int
    {
        // Strategy 1: Scan preamble rows for a date range (Gusto format)
        $preambleYear = $this->detectYearFromPreamble($rows, $headerRowIndex);

        if ($preambleYear) {
            return $preambleYear;
        }

        // Strategy 2: Scan data rows for dates
        return $this->detectYearFromData($rows, $headerRowIndex, $columnMapping);
    }

    /**
     * Look for year in preamble rows (e.g. Gusto "Date Range","01/01/2025","12/31/2025").
     */
    private function detectYearFromPreamble(array $rows, int $headerRowIndex): ?int
    {
        for ($i = 0; $i < $headerRowIndex; $i++) {
            $row = $rows[$i] ?? [];
            $joined = implode(' ', array_map(fn($c) => trim($c ?? ''), $row));

            // Look for date range patterns
            if (preg_match('/(?:date\s*range|time\s*period|period)[^0-9]*(\d{4})/i', $joined, $matches)) {
                return (int) $matches[1];
            }

            // Look for standalone year patterns like "2025:" or "(01/01/2025"
            if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $joined, $matches)) {
                return (int) $matches[3];
            }
        }

        return null;
    }

    /**
     * Scan data rows for date values to infer the tax year.
     * Takes the most common year found in the first 10 data rows.
     */
    private function detectYearFromData(array $rows, int $headerRowIndex, array $columnMapping): ?int
    {
        $dataRows = array_slice($rows, $headerRowIndex + 1, 10);
        $dateColIndex = $columnMapping['date'] ?? null;

        if ($dateColIndex === null || $dateColIndex < 0) {
            // Try to find a date-like column by scanning
            $headers = $rows[$headerRowIndex] ?? [];

            foreach ($headers as $idx => $header) {
                $h = strtolower(trim($header ?? ''));

                if (in_array($h, ['date', 'posting date', 'trans date', 'transaction date', 'check date'])) {
                    $dateColIndex = $idx;
                    break;
                }
            }
        }

        if ($dateColIndex === null || $dateColIndex < 0) {
            return null;
        }

        $years = [];

        foreach ($dataRows as $row) {
            $dateStr = trim($row[$dateColIndex] ?? '');

            if (empty($dateStr)) {
                continue;
            }

            $parsed = $this->parseDate($dateStr);

            if ($parsed) {
                $year = (int) substr($parsed, 0, 4);
                $years[] = $year;
            }
        }

        if (empty($years)) {
            return null;
        }

        // Return the most common year
        $counts = array_count_values($years);
        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * Import transactions from parsed CSV rows into a tax year.
     *
     * @param TaxYear $taxYear
     * @param array $rows Parsed CSV rows (including preamble and header rows)
     * @param array $columnMapping Maps internal names to CSV column indices
     * @param string $filename Original filename for the import record
     * @param int|null $csvTemplateId Optional CSV template ID
     * @param int $headerRowIndex Index of the header row (rows before this are preamble)
     * @param string|null $fallbackDate Date to use when no date column is mapped (Y-m-d)
     * @return Import The created import record with final counts
     */
    public function import(
        TaxYear $taxYear,
        array $rows,
        array $columnMapping,
        string $filename,
        ?int $csvTemplateId = null,
        int $headerRowIndex = 0,
        ?string $fallbackDate = null
    ): Import {
        $import = Import::create([
            'tax_year_id' => $taxYear->id,
            'csv_template_id' => $csvTemplateId,
            'original_filename' => $filename,
            'imported_at' => now(),
        ]);

        // Data rows start after the header row
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $rowsTotal = 0;
        $rowsMatched = 0;
        $rowsUnmatched = 0;
        $rowsIgnored = 0;

        $ignoredBucketIds = Bucket::where('behavior', 'ignored')
            ->pluck('id')
            ->toArray();

        $hasDateColumn = isset($columnMapping['date']) && $columnMapping['date'] !== null && $columnMapping['date'] !== -1;

        DB::beginTransaction();

        try {
            foreach ($dataRows as $row) {
                $description = $row[$columnMapping['description']] ?? null;
                $amount = $row[$columnMapping['amount']] ?? null;

                // Get date from column or use fallback
                $dateRaw = $hasDateColumn ? ($row[$columnMapping['date']] ?? null) : null;

                if (empty($description) || $amount === null || $amount === '') {
                    continue;
                }

                // Clean the amount
                $cleanAmount = preg_replace('/[^0-9.\-]/', '', $amount);

                if (!is_numeric($cleanAmount)) {
                    continue;
                }

                $rowsTotal++;

                // Parse date or use fallback
                $parsedDate = null;

                if ($dateRaw) {
                    $parsedDate = $this->parseDate($dateRaw);
                } elseif ($fallbackDate) {
                    $parsedDate = $fallbackDate;
                }

                if (!$parsedDate) {
                    continue;
                }

                // Run the matcher
                $matches = $this->matcher->match($description);

                $matchType = 'unmatched';
                $isIgnored = false;

                if (!empty($matches)) {
                    $matchType = 'auto';

                    $matchedBucketIds = array_column($matches, 'bucket_id');
                    $isIgnored = empty(array_diff($matchedBucketIds, $ignoredBucketIds));
                }

                $transaction = Transaction::create([
                    'tax_year_id' => $taxYear->id,
                    'import_id' => $import->id,
                    'date' => $parsedDate,
                    'description' => $description,
                    'amount' => $cleanAmount,
                    'match_type' => $matchType,
                ]);

                foreach ($matches as $match) {
                    $transaction->buckets()->attach($match['bucket_id'], [
                        'assigned_via' => 'pattern',
                        'bucket_pattern_id' => $match['bucket_pattern_id'],
                    ]);
                }

                if ($matchType === 'unmatched') {
                    $rowsUnmatched++;
                } elseif ($isIgnored) {
                    $rowsIgnored++;
                } else {
                    $rowsMatched++;
                }
            }

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
            'Y-m-d H:i:s', // 2024-01-05 12:00:00
            'Y-m-d\TH:i:s', // 2024-01-05T12:00:00
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

        try {
            $parsed = new \DateTime(trim($date));
            return $parsed->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}