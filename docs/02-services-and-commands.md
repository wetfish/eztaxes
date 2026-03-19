# Services & Commands

## Service Classes

### CsvImporter

**Location:** `laravel/app/Services/CsvImporter.php`

The core CSV parsing and bank transaction import service. Handles file parsing, header detection, year detection, and transaction import.

Key capabilities: delimiter auto-detection (comma vs tab), preamble scanning to find the actual header row past metadata rows (using a list of known headers from bank, Gusto, and crypto formats), tax year detection from preamble date ranges (Gusto) or transaction dates (bank CSVs), and multi-format date parsing.

The `import()` method accepts a `headerRowIndex` (to skip preamble) and optional `fallbackDate` (for CSVs without a date column, defaults to Dec 31 of the tax year). The entire import is wrapped in a database transaction for rollback safety.

### PayrollImporter

**Location:** `laravel/app/Services/PayrollImporter.php`

Parses all three Gusto CSV formats into `payroll_entries` rows.

Three import methods: `importEmployee()`, `importUsContractor()`, and `importIntlContractor()`. Each uses `buildColumnMap()` to resolve header names to column indices dynamically, so the importer adapts if Gusto changes column ordering.

Includes `isSummaryRow()` which filters out Gusto's aggregation rows ("Grand totals", "Total Report", "All contractors", etc.) using both exact matching and prefix matching to prevent double-counting.

Employee payroll imports store all Gusto columns: gross earnings, employee deductions, employer contributions, employee taxes, employer taxes, net pay, employer cost, check amount, and the pay period info (from the Payroll column) as notes. Tax reconciliation entries (zero gross pay, negative employer taxes) import naturally and self-correct when totals are summed.

### TransactionMatcher

**Location:** `laravel/app/Services/TransactionMatcher.php`

Loads all active BucketPatterns ordered by priority (lowest first). For each transaction description, runs all patterns using `preg_match("/$pattern/i", $description)` and returns every matching bucket — not just the first. This supports multi-bucket tagging while preserving priority ordering.

### TaxYearCalculator

**Location:** `laravel/app/Services/TaxYearCalculator.php`

Recalculates cached `total_income` and `total_expenses` on a TaxYear record. Sums distinct transactions (each amount counted once regardless of how many buckets it belongs to) where the transaction belongs to at least one bucket with `behavior: normal`. Called after imports, manual bucket assignments, and import deletions.

### PriceLookupService

**Location:** `laravel/app/Services/PriceLookupService.php`

Fetches December 31 year-end prices for balance sheet items. Uses CryptoCompare (free, no API key required) for crypto prices and Alpha Vantage (free tier, 25 calls/day) for stock prices. Includes 15-second delays between stock API calls to avoid rate limiting. Detects Alpha Vantage rate limit responses (HTTP 200 with Note/Information key in JSON).

## CSV Detection Pipeline

When a CSV is uploaded to the global import page:

1. The file is parsed with auto-detected delimiter (comma vs tab).
2. `findHeaderRow()` scans all rows for known column names and returns the index of the first row with 2+ matches. Rows before this are preamble.
3. Seeded CSV templates are checked via `CsvTemplate::matchesHeaders()` — if all of a template's `detection_headers` are found in the CSV headers, it's a match.
4. If a seeded template matches, column mapping is resolved from header names to indices. If not, `autoDetectColumns()` uses a two-pass priority system (preferred matches first, then aliases).
5. Tax year is detected from preamble date ranges (Gusto) or the most common year in the first 10 data rows (bank CSVs).
6. The `import_module` is determined from the template route mapping: Gusto templates → payroll, everything else → bank.
7. On confirmation, the data routes to either `PayrollImporter` or `CsvImporter.import()`.

## Transaction Matching Pipeline

When a bank CSV is imported:

1. Each row is saved as a Transaction record.
2. Each transaction's description is run through all active BucketPatterns, ordered by priority.
3. All matching patterns create entries in `bucket_transaction` with `assigned_via: pattern`.
4. Transactions with matches get `match_type: auto`. Those with no matches get `match_type: unmatched`.
5. The Import record is updated with row counts (total, matched, unmatched, ignored).
6. Grand totals on the TaxYear are recalculated from distinct transaction amounts.

## Crypto Cost Basis Calculation

Buy cost basis = (quantity × cost_per_unit) + fee. Sell proceeds = (quantity × price_per_unit) - fee.

When allocating buys to a sell, each allocation's cost basis = allocated quantity × buy's cost_per_unit. Long-term is determined by whether the buy date is more than 365 days before the sell date. A single sell can reference multiple buys with a mix of long-term and short-term allocations.

Tax reporting aggregates allocated sells by calendar year, splitting proceeds and cost basis into short-term and long-term categories. These map to IRS form fields: 1099-DA Box 1f (total proceeds) and 1099-DA Box 1g (total cost basis).

## Reporting Notes

When computing grand totals (total income, total expenses), each transaction's amount is counted exactly once — regardless of how many buckets it belongs to. Per-bucket reports sum the transactions tagged with that bucket, which means bucket-level totals may overlap and won't necessarily add up to the grand total.

Payroll data is tracked in a separate module and is NOT included in bank transaction totals. The three views (bank transactions, payroll, crypto) serve as independent sources of truth that are never merged or double-counted.

## Artisan Commands

All commands are run via Docker Compose:

```bash
docker compose exec app php artisan <command>
```

### `taxyear:create {year}`

Creates a new TaxYear record with the given year and `filing_status: draft`.

### `csv:import {year} {filepath}`

Interactive CSV import from the command line. Detects column headers, shows a preview, lets you select columns, and offers to save the mapping as a template.

### `buckets:import-legacy`

Reads a serialized PHP array from `legacy/buckets.txt`. Creates Bucket and BucketPattern records from the legacy data structure.

### `report {year}`

Generates a per-bucket income/expenses summary for the given tax year. Options: `--unmatched`, `--bucket=slug`, `--all`.

### `taxyear:recalculate {year?}`

Recalculates cached `total_income` and `total_expenses` on the specified tax year. If no year is given, recalculates all years.