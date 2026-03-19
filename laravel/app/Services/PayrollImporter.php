<?php

namespace App\Services;

use App\Models\PayrollEntry;
use App\Models\PayrollImport;
use App\Models\TaxYear;
use Illuminate\Support\Facades\DB;

class PayrollImporter
{
    /**
     * Import a Gusto Employee Payroll CSV.
     */
    public function importEmployee(
        TaxYear $taxYear,
        array $rows,
        int $headerRowIndex,
        string $filename,
        ?int $csvTemplateId = null
    ): PayrollImport {
        $headers = array_map(fn($h) => strtolower(trim($h)), $rows[$headerRowIndex]);
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $colMap = $this->buildColumnMap($headers, [
            'employee' => ['employee'],
            'payroll' => ['payroll'],
            'gross_pay' => ['gross earnings'],
            'employee_deductions' => ['total employee deductions'],
            'employer_contributions' => ['total employer contributions'],
            'employee_taxes' => ['total employee taxes'],
            'employer_taxes' => ['total employer taxes'],
            'net_pay' => ['net pay'],
            'employer_cost' => ['total employer cost'],
            'check_amount' => ['check amount'],
            'date' => ['payroll pay date', 'check date'],
        ]);

        $import = PayrollImport::create([
            'tax_year_id' => $taxYear->id,
            'csv_template_id' => $csvTemplateId,
            'type' => 'employee',
            'original_filename' => $filename,
            'imported_at' => now(),
        ]);

        $rowCount = 0;

        DB::beginTransaction();

        try {
            foreach ($dataRows as $row) {
                $name = trim($row[$colMap['employee']] ?? '');

                if (empty($name)) {
                    continue;
                }

                // Skip summary/total rows
                if ($this->isSummaryRow($name)) {
                    continue;
                }

                $date = isset($colMap['date']) ? $this->parseDate($row[$colMap['date']] ?? '') : null;

                if (!$date) {
                    $date = "{$taxYear->year}-12-31";
                }

                PayrollEntry::create([
                    'tax_year_id' => $taxYear->id,
                    'payroll_import_id' => $import->id,
                    'type' => 'employee',
                    'name' => $name,
                    'date' => $date,
                    'gross_pay' => $this->cleanNumeric($row[$colMap['gross_pay']] ?? '0'),
                    'employee_deductions' => $this->cleanNumeric($row[$colMap['employee_deductions'] ?? null] ?? '0'),
                    'employer_contributions' => $this->cleanNumeric($row[$colMap['employer_contributions'] ?? null] ?? '0'),
                    'employee_taxes' => $this->cleanNumeric($row[$colMap['employee_taxes'] ?? null] ?? '0'),
                    'employer_taxes' => $this->cleanNumeric($row[$colMap['employer_taxes'] ?? null] ?? '0'),
                    'net_pay' => $this->cleanNumeric($row[$colMap['net_pay'] ?? null] ?? '0'),
                    'employer_cost' => $this->cleanNumeric($row[$colMap['employer_cost'] ?? null] ?? '0'),
                    'check_amount' => $this->cleanNumeric($row[$colMap['check_amount'] ?? null] ?? '0'),
                    'notes' => trim($row[$colMap['payroll'] ?? null] ?? '') ?: null,
                ]);

                $rowCount++;
            }

            $import->update(['rows_total' => $rowCount]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $import->delete();
            throw $e;
        }

        return $import;
    }

    /**
     * Import a Gusto US Contractors CSV.
     */
    public function importUsContractor(
        TaxYear $taxYear,
        array $rows,
        int $headerRowIndex,
        string $filename,
        ?int $csvTemplateId = null
    ): PayrollImport {
        $headers = array_map(fn($h) => strtolower(trim($h)), $rows[$headerRowIndex]);
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $colMap = $this->buildColumnMap($headers, [
            'last_name' => ['last name'],
            'first_name' => ['first name'],
            'department' => ['department'],
            'tips_payment' => ['tips (payment)', 'tips'],
            'tips_cash' => ['tips (cash)'],
            'total_amount' => ['total amount'],
        ]);

        $import = PayrollImport::create([
            'tax_year_id' => $taxYear->id,
            'csv_template_id' => $csvTemplateId,
            'type' => 'us_contractor',
            'original_filename' => $filename,
            'imported_at' => now(),
        ]);

        $rowCount = 0;

        DB::beginTransaction();

        try {
            foreach ($dataRows as $row) {
                $lastName = trim($row[$colMap['last_name']] ?? '');
                $firstName = trim($row[$colMap['first_name'] ?? null] ?? '');

                if (empty($lastName) && empty($firstName)) {
                    continue;
                }

                $name = $firstName ? "{$firstName} {$lastName}" : $lastName;

                // Skip summary/total rows
                if ($this->isSummaryRow($name)) {
                    continue;
                }

                PayrollEntry::create([
                    'tax_year_id' => $taxYear->id,
                    'payroll_import_id' => $import->id,
                    'type' => 'us_contractor',
                    'name' => $name,
                    'date' => "{$taxYear->year}-12-31",
                    'gross_pay' => $this->cleanNumeric($row[$colMap['total_amount']] ?? '0'),
                    'department' => trim($row[$colMap['department'] ?? null] ?? '') ?: null,
                    'tips_payment' => $this->cleanNumeric($row[$colMap['tips_payment'] ?? null] ?? '0'),
                    'tips_cash' => $this->cleanNumeric($row[$colMap['tips_cash'] ?? null] ?? '0'),
                ]);

                $rowCount++;
            }

            $import->update(['rows_total' => $rowCount]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $import->delete();
            throw $e;
        }

        return $import;
    }

    /**
     * Import a Gusto International Contractors CSV.
     */
    public function importIntlContractor(
        TaxYear $taxYear,
        array $rows,
        int $headerRowIndex,
        string $filename,
        ?int $csvTemplateId = null
    ): PayrollImport {
        $headers = array_map(fn($h) => strtolower(trim($h)), $rows[$headerRowIndex]);
        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $colMap = $this->buildColumnMap($headers, [
            'contractor_name' => ['contractor name'],
            'processing_date' => ['processing date'],
            'wage_type' => ['wage type'],
            'usd_amount' => ['usd amount'],
            'hours' => ['hours'],
            'hourly_rate' => ['hourly rate'],
            'currency' => ['currency'],
            'foreign_amount' => ['amount'],
            'memo' => ['memo'],
            'payment_status' => ['payment status'],
        ]);

        $import = PayrollImport::create([
            'tax_year_id' => $taxYear->id,
            'csv_template_id' => $csvTemplateId,
            'type' => 'intl_contractor',
            'original_filename' => $filename,
            'imported_at' => now(),
        ]);

        $rowCount = 0;

        DB::beginTransaction();

        try {
            foreach ($dataRows as $row) {
                $name = trim($row[$colMap['contractor_name']] ?? '');

                if (empty($name)) {
                    continue;
                }

                // Skip summary/total rows
                if ($this->isSummaryRow($name)) {
                    continue;
                }

                $date = isset($colMap['processing_date']) ? $this->parseDate($row[$colMap['processing_date']] ?? '') : null;

                if (!$date) {
                    $date = "{$taxYear->year}-12-31";
                }

                $usdAmount = $this->cleanNumeric($row[$colMap['usd_amount']] ?? '0');

                PayrollEntry::create([
                    'tax_year_id' => $taxYear->id,
                    'payroll_import_id' => $import->id,
                    'type' => 'intl_contractor',
                    'name' => $name,
                    'date' => $date,
                    'gross_pay' => $usdAmount,
                    'wage_type' => trim($row[$colMap['wage_type'] ?? null] ?? '') ?: null,
                    'currency' => trim($row[$colMap['currency'] ?? null] ?? '') ?: null,
                    'foreign_amount' => $this->cleanNumericNullable($row[$colMap['foreign_amount'] ?? null] ?? null),
                    'payment_status' => trim($row[$colMap['payment_status'] ?? null] ?? '') ?: null,
                    'hours' => $this->cleanNumericNullable($row[$colMap['hours'] ?? null] ?? null),
                    'hourly_rate' => $this->cleanNumericNullable($row[$colMap['hourly_rate'] ?? null] ?? null),
                    'notes' => trim($row[$colMap['memo'] ?? null] ?? '') ?: null,
                ]);

                $rowCount++;
            }

            $import->update(['rows_total' => $rowCount]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $import->delete();
            throw $e;
        }

        return $import;
    }

    /**
     * Check if a row name indicates a summary/totals row that should be skipped.
     */
    private function isSummaryRow(string $name): bool
    {
        $lower = strtolower(trim($name));

        $exactMatches = [
            'grand total', 'grand totals',
            'totals', 'total', 'total report',
            'subtotal', 'sub total', 'summary',
            'all contractors', 'all employees',
        ];

        if (in_array($lower, $exactMatches)) {
            return true;
        }

        // Catch variations like "Total Report", "Grand Total (USD)", etc.
        if (str_starts_with($lower, 'total ') || str_starts_with($lower, 'grand total') || str_starts_with($lower, 'all contractors') || str_starts_with($lower, 'all employees')) {
            return true;
        }

        return false;
    }

    /**
     * Build a column index map from header names.
     * Each entry maps a logical name to its column index.
     */
    private function buildColumnMap(array $headerRow, array $fieldAliases): array
    {
        $map = [];

        foreach ($fieldAliases as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search(strtolower($alias), $headerRow);

                if ($idx !== false) {
                    $map[$field] = $idx;
                    break;
                }
            }
        }

        return $map;
    }

    private function cleanNumeric(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($cleaned) ? (float) $cleaned : 0;
    }

    private function cleanNumericNullable(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', $value);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    private function parseDate(string $date): ?string
    {
        $formats = [
            'n/j/Y', 'n/j/y', 'm/d/Y', 'm/d/y',
            'Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s',
            'M d, Y', 'M j, Y', 'F d, Y', 'F j, Y', 'd-M-Y',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, trim($date));

            if ($parsed && $parsed->format($format) === trim($date)) {
                return $parsed->format('Y-m-d');
            }
        }

        try {
            return (new \DateTime(trim($date)))->format('Y-m-d');
        } catch (\Exception) {
            return null;
        }
    }
}