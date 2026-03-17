<?php

namespace App\Http\Controllers;

use App\Models\CsvTemplate;
use App\Models\Import;
use App\Models\TaxYear;
use App\Services\CsvImporter;
use App\Services\TaxYearCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportController extends Controller
{
    public function create(int $year)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();
        $templates = CsvTemplate::orderBy('name')->get();

        return view('imports.upload', compact('taxYear', 'templates'));
    }

    public function upload(Request $request, int $year, CsvImporter $importer)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $filename = $file->getClientOriginalName();

        // Store temporarily for the confirmation step
        $storedFile = $file->store('temp-imports');

        // Parse the file
        $rows = $importer->parseFile(Storage::path($storedFile));

        if (empty($rows)) {
            Storage::delete($storedFile);
            return back()->with('error', 'CSV file is empty or could not be parsed.');
        }

        $headers = $importer->detectHeaders($rows);
        $preview = array_slice($rows, 1, 5);
        $rowCount = count($rows) - 1;

        // Try to auto-detect column mapping
        $mappedColumns = $this->autoDetectColumns($headers);

        // If a template was selected, use its mapping
        $templateId = $request->input('csv_template_id');

        if ($templateId) {
            $template = CsvTemplate::find($templateId);

            if ($template) {
                $mappedColumns = $template->column_mapping;
            }
        }

        return view('imports.confirm', compact(
            'taxYear', 'headers', 'preview', 'rowCount',
            'filename', 'storedFile', 'mappedColumns'
        ));
    }

    public function process(Request $request, int $year, CsvImporter $importer)
    {
        $taxYear = TaxYear::where('year', $year)->firstOrFail();

        $request->validate([
            'stored_file' => 'required|string',
            'filename' => 'required|string',
            'col_date' => 'required|integer|min:0',
            'col_amount' => 'required|integer|min:0',
            'col_description' => 'required|integer|min:0',
        ]);

        $storedFile = $request->input('stored_file');
        $filepath = Storage::path($storedFile);

        if (!file_exists($filepath)) {
            return redirect("/tax-years/{$year}/import")->with('error', 'Uploaded file expired. Please upload again.');
        }

        $columnMapping = [
            'date' => (int) $request->input('col_date'),
            'amount' => (int) $request->input('col_amount'),
            'description' => (int) $request->input('col_description'),
        ];

        // Save template if requested
        $csvTemplateId = null;

        if ($request->input('save_template') && $request->input('template_name')) {
            $template = CsvTemplate::create([
                'name' => $request->input('template_name'),
                'column_mapping' => $columnMapping,
            ]);

            $csvTemplateId = $template->id;
        }

        // Parse and import
        $rows = $importer->parseFile($filepath);

        $import = $importer->import(
            $taxYear,
            $rows,
            $columnMapping,
            $request->input('filename'),
            $csvTemplateId
        );

        // Clean up the temp file
        Storage::delete($storedFile);

        return redirect("/tax-years/{$year}")
            ->with('success', "Imported {$import->rows_total} transactions ({$import->rows_matched} matched, {$import->rows_unmatched} unmatched, {$import->rows_ignored} ignored).");
    }

    public function destroy(int $id, TaxYearCalculator $calculator)
    {
        $import = Import::findOrFail($id);
        $taxYear = $import->taxYear;
        $year = $taxYear->year;

        $import->delete();

        // Recalculate totals after removing transactions
        $calculator->recalculate($taxYear);

        return redirect("/tax-years/{$year}")->with('success', 'Import and all associated transactions deleted.');
    }

    /**
     * Attempt to auto-detect column mapping from header names.
     */
    private function autoDetectColumns(array $headers): array
    {
        $mapping = ['date' => 0, 'amount' => 1, 'description' => 2];

        $headerLower = array_map('strtolower', array_map('trim', $headers));

        foreach ($headerLower as $index => $header) {
            if (in_array($header, ['date', 'posting date', 'trans date', 'transaction date'])) {
                $mapping['date'] = $index;
            }

            if (in_array($header, ['amount', 'debit', 'credit', 'transaction amount'])) {
                $mapping['amount'] = $index;
            }

            if (in_array($header, ['description', 'memo', 'transaction description', 'details', 'narrative'])) {
                $mapping['description'] = $index;
            }
        }

        return $mapping;
    }
}