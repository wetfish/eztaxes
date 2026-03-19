<?php

namespace App\Http\Controllers;

use App\Models\CsvTemplate;
use App\Models\Import;
use App\Models\TaxYear;
use App\Services\CsvImporter;
use App\Services\PayrollImporter;
use App\Services\TaxYearCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    /**
     * Map seeded template names to their import module and payroll type.
     */
    private const TEMPLATE_ROUTES = [
        'Gusto Employee Payroll' => ['module' => 'payroll', 'type' => 'employee'],
        'Gusto US Contractors' => ['module' => 'payroll', 'type' => 'us_contractor'],
        'Gusto International Contractors' => ['module' => 'payroll', 'type' => 'intl_contractor'],
    ];

    public function create()
    {
        $templates = CsvTemplate::where('is_seeded', false)->orderBy('name')->get();

        return view('imports.upload', compact('templates'));
    }

    /**
     * Legacy route — redirect to global import page.
     */
    public function createLegacy(int $year)
    {
        return redirect('/import');
    }

    public function upload(Request $request, CsvImporter $importer)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $filename = $file->getClientOriginalName();

        $storedFile = $file->store('temp-imports');

        $rows = $importer->parseFile(Storage::path($storedFile));

        if (empty($rows)) {
            Storage::delete($storedFile);
            return back()->with('error', 'CSV file is empty or could not be parsed.');
        }

        // Scan for the actual header row (skips preamble)
        $detected = $importer->detectHeaders($rows);
        $headers = $detected['headers'];
        $headerRowIndex = $detected['headerRowIndex'];

        // Preview rows are the data rows after the header
        $preview = array_slice($rows, $headerRowIndex + 1, 5);
        $rowCount = count($rows) - $headerRowIndex - 1;

        // Try to auto-detect a seeded template
        $detectedTemplate = $this->detectTemplate($headers);
        $mappedColumns = null;

        if ($detectedTemplate) {
            $mappedColumns = $this->resolveTemplateMapping($detectedTemplate, $headers);
        }

        // If a user explicitly selected a template, use that instead
        $templateId = $request->input('csv_template_id');

        if ($templateId) {
            $template = CsvTemplate::find($templateId);

            if ($template) {
                $detectedTemplate = $template;
                $mappedColumns = $this->resolveTemplateMapping($template, $headers);
            }
        }

        // Fall back to generic auto-detect if no template matched
        if (!$mappedColumns) {
            $mappedColumns = $this->autoDetectColumns($headers);
        }

        // Determine the import module
        $importModule = 'bank';

        if ($detectedTemplate && isset(self::TEMPLATE_ROUTES[$detectedTemplate->name])) {
            $importModule = self::TEMPLATE_ROUTES[$detectedTemplate->name]['module'];
        }

        // Auto-detect the tax year
        $detectedYear = $importer->detectYear($rows, $headerRowIndex, $mappedColumns);
        $taxYears = TaxYear::orderByDesc('year')->get();

        return view('imports.confirm', compact(
            'headers', 'preview', 'rowCount',
            'filename', 'storedFile', 'mappedColumns', 'headerRowIndex',
            'detectedTemplate', 'importModule', 'detectedYear', 'taxYears'
        ));
    }

    public function process(Request $request, CsvImporter $csvImporter, PayrollImporter $payrollImporter)
    {
        $request->validate([
            'stored_file' => 'required|string',
            'filename' => 'required|string',
            'header_row_index' => 'required|integer|min:0',
            'import_module' => 'required|string|in:bank,payroll',
            'tax_year' => 'required|integer|min:2000|max:2099',
        ]);

        $year = (int) $request->input('tax_year');

        // Find or create the tax year
        $taxYear = TaxYear::firstOrCreate(
            ['year' => $year],
            ['filing_status' => 'draft', 'total_income' => 0, 'total_expenses' => 0]
        );

        $storedFile = $request->input('stored_file');
        $filepath = Storage::path($storedFile);

        if (!file_exists($filepath)) {
            return redirect('/import')->with('error', 'Uploaded file expired. Please upload again.');
        }

        $headerRowIndex = (int) $request->input('header_row_index');
        $importModule = $request->input('import_module');

        // Route to the correct importer
        if ($importModule === 'payroll') {
            return $this->processPayroll($request, $taxYear, $payrollImporter, $csvImporter, $filepath, $headerRowIndex);
        }

        return $this->processBank($request, $taxYear, $csvImporter, $filepath, $headerRowIndex);
    }

    /**
     * Process a payroll CSV import.
     */
    private function processPayroll(
        Request $request,
        TaxYear $taxYear,
        PayrollImporter $importer,
        CsvImporter $csvImporter,
        string $filepath,
        int $headerRowIndex
    ) {
        $year = $taxYear->year;
        $filename = $request->input('filename');
        $csvTemplateId = $request->input('detected_template_id') ? (int) $request->input('detected_template_id') : null;

        // Determine the payroll type from the detected template
        $templateName = null;

        if ($csvTemplateId) {
            $template = CsvTemplate::find($csvTemplateId);
            $templateName = $template?->name;
        }

        $payrollType = self::TEMPLATE_ROUTES[$templateName]['type'] ?? null;

        if (!$payrollType) {
            Storage::delete($request->input('stored_file'));
            return redirect('/import')->with('error', 'Could not determine payroll import type.');
        }

        $rows = $csvImporter->parseFile($filepath);

        $import = match ($payrollType) {
            'employee' => $importer->importEmployee($taxYear, $rows, $headerRowIndex, $filename, $csvTemplateId),
            'us_contractor' => $importer->importUsContractor($taxYear, $rows, $headerRowIndex, $filename, $csvTemplateId),
            'intl_contractor' => $importer->importIntlContractor($taxYear, $rows, $headerRowIndex, $filename, $csvTemplateId),
        };

        Storage::delete($request->input('stored_file'));

        $typeLabel = match ($payrollType) {
            'employee' => 'employee payroll',
            'us_contractor' => 'US contractor',
            'intl_contractor' => 'international contractor',
        };

        return redirect("/payroll/{$year}")
            ->with('success', "Imported {$import->rows_total} {$typeLabel} entries.");
    }

    /**
     * Process a bank transaction CSV import.
     */
    private function processBank(
        Request $request,
        TaxYear $taxYear,
        CsvImporter $importer,
        string $filepath,
        int $headerRowIndex
    ) {
        $year = $taxYear->year;

        $request->validate([
            'col_date' => 'required|integer|min:-1',
            'col_amount' => 'required|integer|min:0',
            'col_description' => 'required|integer|min:0',
        ]);

        $colDate = (int) $request->input('col_date');

        $columnMapping = [
            'date' => $colDate >= 0 ? $colDate : null,
            'amount' => (int) $request->input('col_amount'),
            'description' => (int) $request->input('col_description'),
        ];

        $fallbackDate = null;

        if ($colDate < 0) {
            $fallbackDate = "{$year}-12-31";
        }

        // Save template if requested
        $csvTemplateId = $request->input('detected_template_id') ? (int) $request->input('detected_template_id') : null;

        if (!$csvTemplateId && $request->input('save_template') && $request->input('template_name')) {
            $template = CsvTemplate::create([
                'name' => $request->input('template_name'),
                'column_mapping' => $columnMapping,
            ]);

            $csvTemplateId = $template->id;
        }

        $rows = $importer->parseFile($filepath);

        $import = $importer->import(
            $taxYear,
            $rows,
            $columnMapping,
            $request->input('filename'),
            $csvTemplateId,
            $headerRowIndex,
            $fallbackDate
        );

        Storage::delete($request->input('stored_file'));

        return redirect("/tax-years/{$year}")
            ->with('success', "Imported {$import->rows_total} transactions ({$import->rows_matched} matched, {$import->rows_unmatched} unmatched, {$import->rows_ignored} ignored).");
    }

    public function destroy(int $id, TaxYearCalculator $calculator)
    {
        $import = Import::findOrFail($id);
        $taxYear = $import->taxYear;
        $year = $taxYear->year;

        $import->delete();

        $calculator->recalculate($taxYear);

        return redirect("/tax-years/{$year}")->with('success', 'Import and all associated transactions deleted.');
    }

    /**
     * Try to match a seeded template against the CSV headers.
     */
    private function detectTemplate(array $headers): ?CsvTemplate
    {
        $seededTemplates = CsvTemplate::where('is_seeded', true)->get();

        foreach ($seededTemplates as $template) {
            if ($template->matchesHeaders($headers)) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Resolve a template's header-name-based mapping to actual column indices.
     */
    private function resolveTemplateMapping(CsvTemplate $template, array $headers): array
    {
        $mapping = $template->column_mapping;
        $lowered = array_map(fn($h) => strtolower(trim($h)), $headers);

        if (isset($mapping['date_header'])) {
            $idx = array_search(strtolower($mapping['date_header']), $lowered);
            $mapping['date'] = $idx !== false ? $idx : ($mapping['date'] ?? -1);
        }

        if (isset($mapping['amount_header'])) {
            $idx = array_search(strtolower($mapping['amount_header']), $lowered);
            $mapping['amount'] = $idx !== false ? $idx : ($mapping['amount'] ?? 0);
        }

        if (isset($mapping['description_header'])) {
            $idx = array_search(strtolower($mapping['description_header']), $lowered);
            $mapping['description'] = $idx !== false ? $idx : ($mapping['description'] ?? 0);
        }

        if (!isset($mapping['date']) || $mapping['date'] === null) {
            $mapping['date'] = -1;
        }

        return $mapping;
    }

    /**
     * Generic auto-detect column mapping from header names.
     */
    private function autoDetectColumns(array $headers): array
    {
        $mapping = ['date' => -1, 'amount' => 0, 'description' => 0];

        $headerLower = array_map('strtolower', array_map('trim', $headers));

        // Date: prefer exact matches first, then aliases
        $datePreferred = ['date', 'posting date', 'transaction date'];
        $dateAliases = ['trans date', 'payroll pay date', 'processing date', 'check date'];

        // Amount: prefer exact matches first, then aliases
        $amountPreferred = ['amount', 'transaction amount'];
        $amountAliases = ['debit', 'credit', 'check amount', 'net pay', 'total amount', 'usd amount'];

        // Description: prefer exact matches first, then aliases
        $descPreferred = ['description', 'transaction description'];
        $descAliases = ['memo', 'details', 'narrative', 'employee', 'contractor name'];

        $mapping['date'] = $this->findBestColumn($headerLower, $datePreferred, $dateAliases) ?? -1;
        $mapping['amount'] = $this->findBestColumn($headerLower, $amountPreferred, $amountAliases) ?? 0;
        $mapping['description'] = $this->findBestColumn($headerLower, $descPreferred, $descAliases) ?? 0;

        // Last resort fallback for Gusto US contractors
        if ($mapping['description'] === 0) {
            foreach ($headerLower as $index => $header) {
                if ($header === 'last name') {
                    $mapping['description'] = $index;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Find the best matching column, preferring exact matches over aliases.
     */
    private function findBestColumn(array $headerLower, array $preferred, array $aliases): ?int
    {
        // First pass: preferred (exact) matches
        foreach ($headerLower as $index => $header) {
            if (in_array($header, $preferred)) {
                return $index;
            }
        }

        // Second pass: alias matches
        foreach ($headerLower as $index => $header) {
            if (in_array($header, $aliases)) {
                return $index;
            }
        }

        return null;
    }
}