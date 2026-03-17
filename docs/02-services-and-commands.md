# Services & Commands

## Service Classes

### TransactionMatcher

**Location:** `laravel/app/Services/TransactionMatcher.php`

Loads all active BucketPatterns ordered by priority (lowest first). For each transaction description, runs all patterns using `preg_match("/$pattern/i", $description)` and returns every matching bucket — not just the first. This supports multi-bucket tagging while preserving priority ordering.

### CsvImporter

**Location:** `laravel/app/Services/CsvImporter.php`

Accepts an uploaded CSV file, a TaxYear, and a column mapping (date/amount/description indices). Parses rows, creates Transaction records, runs each through TransactionMatcher, creates pivot entries, and updates the Import summary counts. The entire import is wrapped in a database transaction for rollback safety. Calls TaxYearCalculator after completion.

Handles date parsing with multiple format attempts to accommodate different CSV sources.

### TaxYearCalculator

**Location:** `laravel/app/Services/TaxYearCalculator.php`

Recalculates cached `total_income` and `total_expenses` on a TaxYear record. Sums distinct transactions (each amount counted once regardless of how many buckets it belongs to) where the transaction belongs to at least one bucket with `behavior: normal`. Called after imports, manual bucket assignments, and import deletions.

## Transaction Matching Pipeline

When a CSV is imported:

1. The CSV is parsed and column headers are detected.
2. The user confirms the column mapping (date, amount, description) or selects a saved template.
3. Each row is saved as a Transaction record.
4. Each transaction's description is run through all active BucketPatterns, ordered by priority (lowest first).
5. All matching patterns create entries in `bucket_transaction` with `assigned_via: pattern` and the matching `bucket_pattern_id`.
6. Transactions with at least one match get `match_type: auto`. Those with no matches get `match_type: unmatched`.
7. The Import record is updated with row counts (total, matched, unmatched, ignored).
8. Grand totals on the TaxYear are recalculated from distinct transaction amounts.

## Crypto Cost Basis Calculation

Buy cost basis = (quantity × cost_per_unit) + fee.

Sell proceeds = (quantity × price_per_unit) - fee.

When allocating buys to a sell, each allocation's cost basis = allocated quantity × buy's cost_per_unit. Long-term is determined by whether the buy date is more than 365 days before the sell date. A single sell can reference multiple buys with a mix of long-term and short-term allocations.

Tax reporting aggregates allocated sells by calendar year, splitting proceeds and cost basis into short-term and long-term categories. These map to IRS form fields: 1099-DA Box 1f (total proceeds) and 1099-DA Box 1g (total cost basis).

## Reporting Notes

When computing grand totals (total income, total expenses), each transaction's amount is counted exactly once — regardless of how many buckets it belongs to. Per-bucket reports sum the transactions tagged with that bucket, which means bucket-level totals may overlap and won't necessarily add up to the grand total. This is by design and mirrors how the legacy script worked with its sha256 deduplication.

## Artisan Commands

All commands are run via Docker Compose:

```bash
docker compose exec app php artisan <command>
```

### `taxyear:create {year}`

Creates a new TaxYear record with the given year and `filing_status: draft`.

### `csv:import {year} {filepath}`

Interactive CSV import from the command line. Detects column headers, shows a preview, lets you select columns, and offers to save the mapping as a template. Checks the `legacy/` directory as a fallback for file paths. Inside the Docker container, the legacy directory is volume-mounted at `/var/www/legacy/`.

### `buckets:import-legacy`

Reads a serialized PHP array from `legacy/buckets.txt` (default path, configurable). Creates Bucket and BucketPattern records from the legacy data structure. Uses `unserialize($contents, ['allowed_classes' => false])` for security.

### `report {year}`

Generates a per-bucket income/expenses summary for the given tax year. Options:
- `--unmatched` — show unmatched transactions
- `--bucket=slug` — show single bucket detail
- `--all` — show all transactions

### `taxyear:recalculate {year?}`

Recalculates cached `total_income` and `total_expenses` on the specified tax year. If no year is given, recalculates all years.