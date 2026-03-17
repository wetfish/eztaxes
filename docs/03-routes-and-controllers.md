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

### CSV Imports

```text
GET  /tax-years/{year}/import             → ImportController@create
POST /tax-years/{year}/import             → ImportController@upload
POST /tax-years/{year}/import/process     → ImportController@process
DELETE /imports/{id}                       → ImportController@destroy
```

### Balance Sheet

```text
GET  /tax-years/{year}/balance-sheet      → BalanceSheetController@index
POST /tax-years/{year}/balance-sheet      → BalanceSheetController@store
GET  /tax-years/{year}/balance-sheet/copy → BalanceSheetController@copyPreview
POST /tax-years/{year}/balance-sheet/copy → BalanceSheetController@copyProcess
PATCH  /balance-sheet/{id}                → BalanceSheetController@update
DELETE /balance-sheet/{id}                → BalanceSheetController@destroy
```

### Buckets & Patterns

```text
GET  /buckets                             → BucketController@index
POST /buckets                             → BucketController@store
DELETE /buckets/{id}                      → BucketController@destroy
POST /buckets/{id}/patterns               → BucketController@addPattern
DELETE /patterns/{id}                     → BucketController@deletePattern
```

### CSV Templates

```text
GET  /csv-templates                       → CsvTemplateController@index
DELETE /csv-templates/{id}                → CsvTemplateController@destroy
```

### Crypto

```text
GET  /crypto                              → CryptoController@index
POST /crypto                              → CryptoController@store
DELETE /crypto/buys/{id}                  → CryptoController@deleteBuy
DELETE /crypto/sells/{id}                 → CryptoController@deleteSell
GET  /crypto/sells/{id}/allocate          → CryptoController@allocateSell
POST /crypto/sells/{id}/allocate          → CryptoController@storeAllocation
GET  /crypto/{id}                         → CryptoController@show
DELETE /crypto/{id}                       → CryptoController@destroy
POST /crypto/{id}/buys                    → CryptoController@storeBuy
GET  /crypto/{id}/sell                    → CryptoController@createSell
POST /crypto/{id}/sells                   → CryptoController@storeSell
GET  /crypto/{id}/import                  → CryptoController@importForm
POST /crypto/{id}/import                  → CryptoController@importProcess
```

**Routing note:** specific routes like `/crypto/sells/{id}/allocate` and `/crypto/buys/{id}` must be defined before the wildcard `/crypto/{id}` to avoid incorrect matching.

## Controller Responsibilities

### DashboardController

Lists all tax years with income/expense/net summaries. Inline form to create new tax years.

### TaxYearController

**store** — creates a new tax year with `filing_status: draft`.

**show** — displays the year detail page with summary cards (income, expenses, net), expandable bucket breakdown table (click a row to reveal its transactions), unmatched transaction alert, and import history with delete.

### TransactionController

**index** — transaction list with All/Matched/Unmatched filter tabs. Pattern builder at top: enter a regex, test it against all transactions, see match count, then save to a bucket. Manual quick-assign collapsible per unmatched transaction. "Create pattern" button auto-fills the pattern builder from a transaction description.

**assignBucket** — manually assigns a transaction to a bucket.

**createPattern** — saves a new pattern and re-runs matching on all unmatched transactions for the year.

### ImportController

Two-step CSV upload flow:

1. **create/upload** — upload a CSV file, auto-detect columns, select or create a template mapping
2. **process** — confirm mapping and process the import

Uses Laravel Storage for temp files between steps.

### BalanceSheetController

**index** — lists balance sheet items for a tax year. Add form dynamically adjusts fields by asset type (crypto/stock show quantity + price; cash/other show total value). Click a row to expand inline edit. Crypto items show activity hints (tracked holdings, buys/sells for the year). "Copy from {previous year}" button when applicable.

**copyPreview** — shows each item from the previous year as a card with previous values, crypto activity adjustments, and editable fields for the new year. Suggested quantities are pre-calculated from previous quantity ± tracked buys/sells.

**copyProcess** — creates new balance sheet items from the reviewed/adjusted copy form.

### BucketController

**index** — lists all buckets with inline create form. Each bucket shows its patterns in a collapsible section with add/delete. Bucket delete with confirmation.

**addPattern** — validates regex before saving.

### CsvTemplateController

**index** — lists saved CSV templates with delete.

### CryptoController

**index** — lists all crypto assets with current holdings and a form to add new assets.

**show** — asset detail page with summary cards (holdings, cost basis, total proceeds, gain/loss), tax reporting by year (short-term/long-term with IRS form references), sells table (unallocated first with amber highlight, allocated expandable to show buy allocations), buys table, and record buy form. Buttons for "Record Sell" and "Import CSV".

**createSell / storeSell** — sell form with scrollable buy allocation table. Max button fills min(buy remaining, sell total − other allocations). `enforceLimit()` on every keystroke. Total allocated counter updates live.

**allocateSell / storeAllocation** — same allocation UI for retroactive assignment of unallocated sells.

**importForm / importProcess** — CSV upload for crypto transactions. Auto-detects CashApp format columns (Transaction Type for buy/sell distinction). `cleanNumeric()` helper strips commas, currency symbols, and whitespace.

**deleteSell** — deletes a sell and restores allocated quantities on referenced buys.