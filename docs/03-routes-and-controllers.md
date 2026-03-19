# Routes & Controllers

## Route Listing

### Dashboard

```text
GET  /                                    → DashboardController@index
```

### Tax Years

```text
POST /tax-years                           → TaxYearController@store
GET  /tax-years/{year}                    → TaxYearController@show
```

### Transactions

```text
GET  /tax-years/{year}/transactions       → TransactionController@index
POST /transactions/{id}/assign-bucket     → TransactionController@assignBucket
POST /transactions/create-pattern         → TransactionController@createPattern
```

### CSV Imports (Global)

```text
GET  /import                              → ImportController@create
POST /import                              → ImportController@upload
POST /import/process                      → ImportController@process
DELETE /imports/{id}                       → ImportController@destroy
```

Legacy route for backward compatibility:

```text
GET  /tax-years/{year}/import             → ImportController@createLegacy (redirects to /import)
```

### Payroll

```text
GET  /payroll                             → PayrollController@index
GET  /payroll/{year}                      → PayrollController@show
POST /payroll/{year}/toggle-officer       → PayrollController@toggleOfficer
DELETE /payroll/imports/{id}              → PayrollController@destroyImport
```

### Balance Sheet

```text
GET  /tax-years/{year}/balance-sheet      → BalanceSheetController@index
POST /tax-years/{year}/balance-sheet      → BalanceSheetController@store
GET  /tax-years/{year}/balance-sheet/copy → BalanceSheetController@copyPreview
POST /tax-years/{year}/balance-sheet/copy → BalanceSheetController@copyProcess
POST /tax-years/{year}/balance-sheet/fetch-prices → BalanceSheetController@fetchPrices
PATCH  /balance-sheet/{id}               → BalanceSheetController@update
DELETE /balance-sheet/{id}               → BalanceSheetController@destroy
```

### Bucket Groups

```text
POST   /bucket-groups                     → BucketController@storeGroup
DELETE /bucket-groups/{id}               → BucketController@destroyGroup
```

### Buckets

```text
GET    /buckets                           → BucketController@index
POST   /buckets                           → BucketController@store
PATCH  /buckets/{id}/group               → BucketController@updateGroup
DELETE /buckets/{id}                      → BucketController@destroy
```

### Bucket Patterns

```text
POST   /buckets/{id}/patterns            → BucketController@addPattern
DELETE /patterns/{id}                     → BucketController@deletePattern
```

### CSV Templates

```text
GET    /csv-templates                     → CsvTemplateController@index
DELETE /csv-templates/{id}               → CsvTemplateController@destroy
```

### Crypto

```text
GET    /crypto                            → CryptoController@index
POST   /crypto                            → CryptoController@store
DELETE /crypto/buys/{id}                 → CryptoController@deleteBuy
DELETE /crypto/sells/{id}                → CryptoController@deleteSell
GET    /crypto/sells/{id}/allocate       → CryptoController@allocateSell
POST   /crypto/sells/{id}/allocate       → CryptoController@storeAllocation
GET    /crypto/{id}                       → CryptoController@show
DELETE /crypto/{id}                       → CryptoController@destroy
POST   /crypto/{id}/buys                 → CryptoController@storeBuy
GET    /crypto/{id}/sell                  → CryptoController@createSell
POST   /crypto/{id}/sells                → CryptoController@storeSell
GET    /crypto/{id}/import               → CryptoController@importForm
POST   /crypto/{id}/import               → CryptoController@importProcess
```

## Controller Responsibilities

### ImportController

The central import controller handling all CSV uploads. Uses a `TEMPLATE_ROUTES` constant to map detected template names to their target module (`bank` or `payroll`). Key flow:

1. `create()` — renders the global upload page at `/import`
2. `upload()` — parses the CSV, scans for headers past preamble rows, detects the format via seeded CSV templates, auto-detects the tax year (from preamble date ranges for Gusto, from transaction dates for bank CSVs), and resolves column mappings
3. `process()` — routes to `processPayroll()` or `processBank()` based on the `import_module` field. Creates the tax year if it doesn't exist.

Auto-detection uses `CsvTemplate::matchesHeaders()` which checks if all of a template's `detection_headers` are present in the CSV. Column mapping for seeded templates uses header-name-based resolution (e.g., `amount_header: "Check amount"` resolved to the actual column index at runtime).

For bank CSVs, `autoDetectColumns()` uses a two-pass priority system: preferred/exact matches first (e.g., "description"), then alias fallbacks (e.g., "memo"), so an exact "Description" column always wins over a "Memo" column.

### PayrollController

Manages the Payroll module with summary views and officer designation.

- `index()` — tax year overview with officer/employee/contractor/tax summaries per year
- `show()` — detailed year view with 1120-S tax line references, per-employee summaries with officer toggle badges, per-contractor summaries, and expandable detail views
- `toggleOfficer()` — updates `is_officer` on all entries matching a given employee name within a tax year. Recalculates Line 7 vs Line 8 split automatically.
- `destroyImport()` — deletes a payroll import and cascading entries

### DashboardController

Lists all tax years with cached `total_income` and `total_expenses` summary cards.

### TaxYearController

Creates tax years and shows detail page with bucket group breakdown computed on the fly, import history with delete links.

### TransactionController

Transaction list with filter tabs (all/matched/unmatched), pattern builder (test regex against live data, see match count, save to bucket), and manual quick-assign for unmatched transactions.

### CryptoController

Full CRUD for crypto assets, buys, and sells. Multi-format CSV import with auto-detection (CashApp vs Coinbase). Sell allocation page for specific identification. Tax year reporting with IRS form references. Balance sheet cross-referencing with discrepancy warnings.

### BalanceSheetController

CRUD for balance sheet items per tax year. Copy-from-previous-year with crypto activity suggestions. Inline edit rows. "Fetch Dec 31 Prices" button calls PriceLookupService (CryptoCompare for crypto, Alpha Vantage for stocks with 15-second delays).

### BucketController

CRUD for bucket groups and buckets. Groups are a separate table from buckets. Assign/move buckets between groups. Pattern management (add/delete).

### CsvTemplateController

Lists all templates (seeded shown with "Built-in" badge, custom templates deletable). Protects seeded templates from deletion.