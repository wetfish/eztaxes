# eztaxes

A Laravel 12 dashboard for managing S-Corp business tax history, cryptocurrency cost basis tracking, and corporate balance sheets. Built to modernize an existing system of CSV reports and PHP regex-based transaction categorization.

## Project Overview

eztaxes replaces a legacy PHP script that used regex patterns to match bank/statement transaction descriptions against categorized "buckets" (e.g., contractors, server bills, office expenses, rent). The matched data was then used to calculate total income and expenses for tax reporting.

This Laravel project wraps that same logic in a proper web application with a database-backed dashboard, import history, and manual review workflow — while preserving the regex pattern matching system for backwards compatibility with existing data and scripts.

The application also includes cryptocurrency cost basis tracking with specific identification for buy/sell allocation, and a corporate balance sheet tracker with year-over-year carry-forward support.

## Features

### Transaction Categorization
- Import bank CSVs with auto-detected column mapping
- Regex pattern matching for automatic transaction categorization
- Multi-bucket tagging (a transaction can belong to multiple buckets)
- Pattern builder UI — test a regex against live data, see match count, then save to a bucket
- Manual quick-assign for individual unmatched transactions
- Reusable CSV column mapping templates

### Cryptocurrency Cost Basis
- Track multiple crypto assets (Bitcoin, Ethereum, etc.)
- Record buy and sell transactions with fees
- CSV import for exchange data (e.g., CashApp format)
- Specific identification — manually allocate which buys each sell draws from
- Auto-calculates cost basis, gain/loss, and long-term vs short-term per allocation
- Tax reporting by year with IRS form field references (1099-DA Box 1f/1g)
- Unallocated sell queue for retroactive cost basis assignment after CSV import

### Balance Sheet
- Track corporate assets (crypto, stocks, cash, other) per tax year
- Manual entry for quantities and December 31 year-end prices
- Crypto items link to the crypto module for activity tracking hints
- Copy balance sheet from previous year with auto-suggested adjustments based on tracked crypto activity (buys/sells)
- Inline editing for quantity, price, and notes

### Artisan Commands
- `taxyear:create {year}` — create a new tax year
- `csv:import {year} {file}` — import a CSV from the command line
- `buckets:import-legacy` — import buckets and patterns from a serialized PHP file
- `report {year}` — generate per-bucket income/expenses report
- `taxyear:recalculate {year?}` — recalculate cached totals

## Tech Stack

- **Framework:** Laravel 12.12.1 (released March 10, 2026; Laravel 12 initially released February 24, 2025)
- **PHP:** 8.5 (Laravel 12 requires 8.2+; PHP 8.5 compatibility added in Laravel 12.8+)
- **Composer:** 2.9.5+ (older versions are incompatible with PHP 8.5)
- **Database:** MySQL 8.0
- **Frontend:** Blade templates (no starter kit), Tailwind CSS via Vite
- **Environment:** Docker (PHP-FPM, Nginx, MySQL, Node for asset builds)

## Setup

### Environment configuration

When running via Docker, update your `laravel/.env` to match the container names:

```ini
DB_CONNECTION=mysql
DB_HOST=eztaxes-db
DB_PORT=3306
DB_DATABASE=eztaxes
DB_USERNAME=eztaxes
DB_PASSWORD=secret
SESSION_DRIVER=file
```

From the host machine, the database is accessible on port `3446` (mapped to avoid conflicts with other projects).

### PHP dependencies

Laravel 12 requires the following PHP extensions: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, and XML. Several of these (Ctype, Fileinfo, Filter, Hash, OpenSSL, PCRE, PDO, Session, Tokenizer) are bundled with `php8.5-common` and do not need separate packages.

Install all required dependencies on Ubuntu/Debian:

```bash
sudo apt install php8.5-cli php8.5-common php8.5-curl php8.5-mbstring php8.5-xml php8.5-bcmath php8.5-zip php8.5-mysql php8.5-fpm unzip -y
```

What each package provides:

| Package | Extensions provided |
|---------|-------------------|
| php8.5-cli | PHP command-line interpreter |
| php8.5-common | Ctype, Fileinfo, Filter, Hash, OpenSSL, PCRE, PDO, Session, Tokenizer |
| php8.5-curl | cURL |
| php8.5-mbstring | Mbstring |
| php8.5-xml | DOM, XML, XMLReader, XMLWriter |
| php8.5-bcmath | BCMath (arbitrary precision math) |
| php8.5-zip | Zip archive support (used by Composer) |
| php8.5-mysql | PDO_MySQL, MySQLi |
| php8.5-fpm | FastCGI Process Manager (for Nginx) |
| unzip | Required by Composer for package extraction |

### Composer

Composer 2.9.5+ is required. Older versions have a known incompatibility with PHP 8.5 (`stream_context_create()` error in `RemoteFilesystem`). If you encounter this error, upgrade Composer:

```bash
composer self-update
```

If self-update fails due to the version being too old, do a fresh install:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

### Tailwind CSS

Tailwind is included via Vite. No dev server is needed — compile assets on the host machine:

```bash
cd laravel
npm install
npm run build
```

Re-run `npm run build` after modifying CSS or Blade templates.

## Migrations

The default Laravel migrations (users, password_reset_tokens, sessions, cache, jobs) have been removed. This project uses a custom schema with file-based sessions (`SESSION_DRIVER=file`).

To set up the database for the first time:

```bash
docker compose exec app php artisan migrate
```

## Data Model

All status and type fields use string columns rather than ENUMs. MySQL ENUMs are notoriously difficult to modify in production migrations. The expected values for each field are documented below and enforced in application logic.

### tax_years

The top-level container. Each record represents one filing year.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| year | int | unique, e.g. 2024 |
| filing_status | string | expected: `draft`, `filed`, `amended` |
| total_income | decimal(12,2) | computed from transactions |
| total_expenses | decimal(12,2) | computed from transactions |
| notes | text | nullable |
| timestamps | | created_at, updated_at |

### csv_templates

Reusable column mappings for different CSV sources (e.g., "Local Credit Union Checking", "Chase Credit Card"). Stored as JSON mapping internal field names to CSV column headers.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Local Credit Union Checking" |
| column_mapping | json | e.g. `{"date": 0, "amount": 2, "description": 1}` |
| timestamps | | created_at, updated_at |

### buckets

Categorization targets. A bucket groups related transactions (e.g., "contractors", "servers", "food"). Buckets are global — they apply across all tax years.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Server Bills" |
| slug | string | unique, e.g. "server-bills" |
| behavior | string | expected: `normal`, `ignored`, `informational` |
| description | text | nullable |
| sort_order | int | default 0 |
| is_active | boolean | default true |
| timestamps | | created_at, updated_at |

Behavior values: `normal` — included in income/expense totals. `ignored` — transactions are categorized but excluded from totals (internal transfers, verification deposits). `informational` — tracked for reference but not counted.

### bucket_patterns

Regex rules for automatic transaction matching. Each pattern belongs to a bucket. During import, patterns are evaluated in priority order (lowest first) against each transaction's description.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_id | FK | references buckets, cascade on delete |
| pattern | string | regex pattern, e.g. `GOOGLE ?\*?CLOUD` |
| priority | int | lower = matched first, default 0 |
| description | string | nullable, human note like "matches AWS invoices" |
| is_active | boolean | default true |
| timestamps | | created_at, updated_at |

### bucket_schedule_lines

Maps buckets to IRS form/line references. A single bucket can appear on multiple forms (e.g., "contractors" might map to Schedule C Line 11 and a 1099-NEC summary).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_id | FK | references buckets, cascade on delete |
| form_name | string | e.g. "Schedule C", "1099-NEC" |
| line_reference | string | e.g. "Line 11", "Box 1" |
| description | string | nullable, e.g. "Contract Labor" |
| timestamps | | created_at, updated_at |

### imports

Record of each CSV file uploaded. All transactions from an import are associated with it. Deleting an import cascades to remove all associated transactions.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | FK | references tax_years, cascade on delete |
| csv_template_id | FK | nullable, references csv_templates, null on delete |
| original_filename | string | as uploaded, e.g. "Accounts-Combined-2024.csv" |
| rows_total | int | default 0 |
| rows_matched | int | default 0 |
| rows_unmatched | int | default 0 |
| rows_ignored | int | default 0 |
| imported_at | timestamp | |
| timestamps | | created_at, updated_at |

### transactions

Individual line items parsed from CSVs. Each transaction belongs to exactly one import and one tax year. A transaction can belong to multiple buckets via the pivot table. The amount is signed: positive = income, negative = expense.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | FK | references tax_years, cascade on delete |
| import_id | FK | references imports, cascade on delete |
| date | date | transaction date |
| description | string | raw text from bank/statement |
| amount | decimal(10,2) | signed: positive = income, negative = expense |
| match_type | string | expected: `auto`, `manual`, `unmatched` |
| timestamps | | created_at, updated_at |

### bucket_transaction (pivot)

Links transactions to buckets. A transaction can belong to multiple buckets. Tracks how the association was made and which pattern matched (if applicable).

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_id | FK | references buckets, cascade on delete |
| transaction_id | FK | references transactions, cascade on delete |
| assigned_via | string | expected: `pattern`, `manual` |
| bucket_pattern_id | FK | nullable, references bucket_patterns, null on delete |
| timestamps | | created_at, updated_at |

Unique index on `[bucket_id, transaction_id]` to prevent duplicate assignments.

### crypto_assets

Cryptocurrency assets tracked in the system.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Bitcoin" |
| symbol | string | unique, e.g. "BTC" |
| timestamps | | created_at, updated_at |

### crypto_buys

Individual cryptocurrency purchase transactions. The `quantity_remaining` field decreases as sells are allocated against this buy.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| crypto_asset_id | FK | references crypto_assets, cascade on delete |
| date | date | purchase date |
| quantity | decimal(16,8) | amount of crypto purchased |
| cost_per_unit | decimal(16,2) | price paid per unit |
| total_cost | decimal(12,2) | (quantity × cost_per_unit) + fee |
| fee | decimal(10,2) | transaction fee, increases cost basis |
| quantity_remaining | decimal(16,8) | decreases as sells reference this buy |
| notes | text | nullable |
| timestamps | | created_at, updated_at |

### crypto_sells

Individual cryptocurrency sale transactions. Cost basis and gain/loss are computed from the buy allocations.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| crypto_asset_id | FK | references crypto_assets, cascade on delete |
| date | date | sale date |
| quantity | decimal(16,8) | amount of crypto sold |
| price_per_unit | decimal(16,2) | price received per unit |
| total_proceeds | decimal(12,2) | (quantity × price_per_unit) - fee |
| fee | decimal(10,2) | transaction fee, reduces proceeds |
| total_cost_basis | decimal(12,2) | sum from allocated buys |
| gain_loss | decimal(12,2) | total_proceeds - total_cost_basis |
| notes | text | nullable |
| timestamps | | created_at, updated_at |

### crypto_buy_sell (pivot)

Links sells to the specific buys they draw from (specific identification method). Each allocation records the quantity drawn, cost basis for that portion, and whether it qualifies as long-term.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| crypto_buy_id | FK | references crypto_buys, cascade on delete |
| crypto_sell_id | FK | references crypto_sells, cascade on delete |
| quantity | decimal(16,8) | how much of this buy was used |
| cost_basis | decimal(12,2) | quantity × buy's cost_per_unit |
| is_long_term | boolean | auto-calculated: buy date > 1 year before sell date |
| timestamps | | created_at, updated_at |

Unique index on `[crypto_buy_id, crypto_sell_id]` to prevent duplicate allocations.

### balance_sheet_items

Corporate balance sheet line items tracked per tax year. Crypto items can link to the crypto module for activity hints.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | FK | references tax_years, cascade on delete |
| crypto_asset_id | FK | nullable, references crypto_assets, null on delete |
| label | string | e.g. "Bitcoin", "Business Checking", "AAPL Stock" |
| asset_type | string | expected: `crypto`, `stock`, `cash`, `other` |
| quantity | decimal(16,8) | nullable, for countable assets |
| unit_price_year_end | decimal(16,2) | nullable, Dec 31 price per unit |
| total_value | decimal(12,2) | quantity × unit_price, or manual entry for cash |
| notes | text | nullable |
| sort_order | int | default 0 |
| timestamps | | created_at, updated_at |

### Cascade delete summary

Deleting a **tax year** removes its imports → transactions → bucket_transaction entries, and its balance sheet items.
Deleting an **import** removes its transactions → bucket_transaction entries.
Deleting a **bucket** removes its patterns, schedule lines, and bucket_transaction entries (transactions themselves are preserved).
Deleting a **crypto asset** removes its buys → crypto_buy_sell entries, its sells → crypto_buy_sell entries, and nullifies linked balance sheet items.
Deleting a **crypto sell** restores the allocated quantities on the referenced buys.

## Model Relationships

```text
TaxYear            → hasMany Imports, hasMany Transactions, hasMany BalanceSheetItems
CsvTemplate        → hasMany Imports
Bucket             → hasMany BucketPatterns, hasMany BucketScheduleLines
                     belongsToMany Transactions (pivot: bucket_transaction)
BucketPattern      → belongsTo Bucket
BucketScheduleLine → belongsTo Bucket
Import             → belongsTo TaxYear, belongsTo CsvTemplate, hasMany Transactions
Transaction        → belongsTo TaxYear, belongsTo Import
                     belongsToMany Buckets (pivot: bucket_transaction)
CryptoAsset        → hasMany CryptoBuys, hasMany CryptoSells
CryptoBuy          → belongsTo CryptoAsset
                     belongsToMany CryptoSells (pivot: crypto_buy_sell)
CryptoSell         → belongsTo CryptoAsset
                     belongsToMany CryptoBuys (pivot: crypto_buy_sell)
BalanceSheetItem   → belongsTo TaxYear, belongsTo CryptoAsset (nullable)
```

## Service Classes

### TransactionMatcher (`laravel/app/Services/TransactionMatcher.php`)

Loads all active BucketPatterns ordered by priority. For each transaction description, runs all patterns and returns every matching bucket (not just the first). This supports multi-bucket tagging while preserving priority ordering.

### CsvImporter (`laravel/app/Services/CsvImporter.php`)

Accepts an uploaded CSV file, a TaxYear, and a column mapping. Parses rows, creates Transaction records, runs each through TransactionMatcher, creates pivot entries, and updates the Import summary counts. Wraps the entire import in a database transaction for rollback safety. Calls TaxYearCalculator after completion.

### TaxYearCalculator (`laravel/app/Services/TaxYearCalculator.php`)

Recalculates totals for a TaxYear by summing distinct transactions (each amount counted once regardless of how many buckets it belongs to). Per-bucket breakdowns show the sum of transactions tagged with that bucket. Called after imports, manual edits, and import deletions.

## Transaction Matching Pipeline

When a CSV is imported:

1. The CSV is parsed and column headers are detected.
2. The user confirms the column mapping (date, amount, description) or selects a saved template.
3. Each row is saved as a Transaction record.
4. Each transaction's description is run through all active BucketPatterns, ordered by priority (lowest first).
5. All matching patterns create entries in bucket_transaction with `assigned_via: pattern` and the matching `bucket_pattern_id`.
6. Transactions with at least one match get `match_type: auto`. Those with no matches get `match_type: unmatched`.
7. The Import record is updated with row counts (total, matched, unmatched, ignored).
8. Grand totals on the TaxYear are recalculated from distinct transaction amounts.

## Crypto Cost Basis Calculation

Buy cost basis = (quantity × cost_per_unit) + fee. Sell proceeds = (quantity × price_per_unit) - fee. When allocating buys to a sell, each allocation's cost basis = allocated quantity × buy's cost_per_unit. Long-term is determined by whether the buy date is more than 365 days before the sell date. A single sell can reference multiple buys with a mix of long-term and short-term allocations.

## Reporting Notes

When computing grand totals (total income, total expenses), each transaction's amount is counted exactly once — regardless of how many buckets it belongs to. Per-bucket reports sum the transactions tagged with that bucket, which means the bucket-level totals may overlap and won't necessarily add up to the grand total. This is by design and mirrors how the legacy script worked with its sha256 deduplication.

Crypto tax reporting aggregates allocated sells by calendar year, splitting proceeds and cost basis into short-term and long-term categories. These map to IRS form fields: 1099-DA Box 1f (total proceeds) and 1099-DA Box 1g (total cost basis).

## Routes

```text
GET    /                                    → Dashboard (year summary cards)
POST   /tax-years                           → Create tax year
GET    /tax-years/{year}                    → Year detail (bucket breakdown, import history)
GET    /tax-years/{year}/transactions       → Transaction list with filters and pattern builder
POST   /transactions/{id}/assign-bucket     → Manual bucket assignment
POST   /transactions/create-pattern         → Save pattern from pattern builder
GET    /tax-years/{year}/import             → CSV upload form
POST   /tax-years/{year}/import             → Upload and preview CSV
POST   /tax-years/{year}/import/process     → Process confirmed import
DELETE /imports/{id}                         → Delete import and cascade transactions
GET    /tax-years/{year}/balance-sheet      → Balance sheet for a tax year
POST   /tax-years/{year}/balance-sheet      → Add balance sheet item
GET    /tax-years/{year}/balance-sheet/copy → Preview copy from previous year
POST   /tax-years/{year}/balance-sheet/copy → Process copy from previous year
PATCH  /balance-sheet/{id}                  → Update balance sheet item
DELETE /balance-sheet/{id}                  → Delete balance sheet item
GET    /buckets                             → Bucket manager with pattern CRUD
POST   /buckets                             → Create bucket
DELETE /buckets/{id}                        → Delete bucket
POST   /buckets/{id}/patterns              → Add pattern to bucket
DELETE /patterns/{id}                       → Delete pattern
GET    /csv-templates                       → CSV template list
DELETE /csv-templates/{id}                  → Delete template
GET    /crypto                              → Crypto assets list
POST   /crypto                              → Create crypto asset
GET    /crypto/{id}                         → Asset detail (buys, sells, tax reporting)
DELETE /crypto/{id}                         → Delete crypto asset
POST   /crypto/{id}/buys                   → Record buy
DELETE /crypto/buys/{id}                   → Delete buy
GET    /crypto/{id}/sell                   → Sell form with buy allocation
POST   /crypto/{id}/sells                  → Record sell
DELETE /crypto/sells/{id}                  → Delete sell (restores buy quantities)
GET    /crypto/sells/{id}/allocate         → Allocate buys to unallocated sell
POST   /crypto/sells/{id}/allocate         → Save allocation
GET    /crypto/{id}/import                 → Crypto CSV import form
POST   /crypto/{id}/import                 → Process crypto CSV import
```

## Directory Structure

```text
eztaxes/
  README.md
  Dockerfile
  docker-compose.yml
  docker/
    nginx/          → default.conf
    php/            → custom.ini
  legacy/           → gitignored, for serialized legacy data files
  laravel/
    app/
      Console/
        Commands/    → ImportLegacyBuckets, CreateTaxYear, ImportCsv,
                       Report, RecalculateTotals
      Models/        → TaxYear, Bucket, BucketPattern, BucketScheduleLine,
                       CsvTemplate, Import, Transaction, CryptoAsset,
                       CryptoBuy, CryptoSell, BalanceSheetItem
      Services/      → TransactionMatcher, CsvImporter, TaxYearCalculator
      Http/
        Controllers/ → DashboardController, TaxYearController,
                       BucketController, TransactionController,
                       ImportController, CsvTemplateController,
                       CryptoController, BalanceSheetController
    resources/
      views/
        layouts/        → app.blade.php
        dashboard/      → index.blade.php
        tax-years/      → show.blade.php
        transactions/   → index.blade.php
        imports/        → upload.blade.php, confirm.blade.php
        buckets/        → index.blade.php
        csv-templates/  → index.blade.php
        crypto/         → index.blade.php, show.blade.php, sell.blade.php,
                          allocate.blade.php, import.blade.php
        balance-sheet/  → index.blade.php, copy.blade.php
```

## Docker Environment

Three services defined in `docker-compose.yml`:

| Container | Image | Purpose | Ports |
|-----------|-------|---------|-------|
| eztaxes-app | php:8.5-fpm (custom) | PHP-FPM with Laravel extensions, Composer, Redis | 9000 (internal) |
| eztaxes-nginx | nginx:alpine | Serves `laravel/public/`, proxies PHP to app | 8010 → 80 |
| eztaxes-db | mysql:8.0 | MySQL database | 3446 → 3306 |

Additional config files:

| File | Purpose |
|------|---------|
| `docker/nginx/default.conf` | Nginx server block pointing to `laravel/public` |
| `docker/php/custom.ini` | PHP overrides (memory_limit = 512M) |

Start the environment:

```bash
docker compose up -d
```

Access the app at `http://localhost:8010`.

Dockerfile and docker-compose.yml adapted from an existing project.

## Planned Features

These features are not implemented yet. Documented here for future reference.

### Multi-user support

Add a `users` table and `user_id` foreign key columns to `tax_years`, `buckets`, and `csv_templates`. Transactions and imports inherit user scoping through their tax_year relationship, so they don't need direct user_id columns. All queries would be scoped by the authenticated user.

### Bucket hierarchy

Add a nullable `parent_id` (self-referencing FK) to `buckets`. This enables roll-up reporting where child buckets (e.g., "gusto payroll", "gusto tax") aggregate under a parent ("payroll"). Query logic would sum child bucket totals into the parent for reports.

### Schedule line management UI

The `bucket_schedule_lines` table exists but there is no UI to add or edit IRS form/line references on buckets yet. When built, this would allow mapping buckets to specific lines on Schedule C, 1099-NEC, and other forms.

### Asset price API integration

The balance sheet currently requires manual entry of Dec 31 asset prices. CoinGecko (free, 10k calls/month) could be used for crypto prices, and Alpha Vantage (free, 25 calls/day) for stock prices. Implementation would add a "Fetch Price" button that suggests a value the user can review and confirm before saving.

### Fixed asset depreciation

The balance sheet currently tracks asset values but does not calculate depreciation. When needed, add fields for date placed in service, depreciation method (straight-line, MACRS, Section 179), useful life, and accumulated depreciation. This would feed into IRS Form 4562.

## Development Notes

### AI-Assisted Development (Claude)

This project is being built with the assistance of Claude (Anthropic). The following conventions must be maintained for consistent output:

**Code blocks** — always use an explicit language identifier on every code block (e.g., `bash`, `ini`, `text`, `php`) and include a descriptive title line before each block. Bare ` ``` ` without a language tag causes consecutive blocks to merge in the Claude chat renderer.

**File paths** — when referencing files for the developer to open, always provide full file paths relative to the repo root that can be opened directly in VSCode/Codium:

```bash
codium README.md
codium docker/php/custom.ini
```

**Artisan commands** — all Laravel artisan commands should be run via Docker Compose. The `app` container's working directory is already set to the Laravel project root, so artisan can be called directly:

```bash
docker compose exec app php artisan make:migration create_example_table
docker compose exec app php artisan migrate
```

**Migration generation** — when generating multiple migrations, add `sleep 1` between commands to ensure unique timestamps and correct ordering:

```bash
docker compose exec app php artisan make:migration create_first_table
sleep 1
docker compose exec app php artisan make:migration create_second_table
```

**Artifact ordering** — when providing downloadable file artifacts alongside a list of `codium` commands, the `codium` commands must be listed in the same order as the artifacts appear in the download list. This prevents confusion when the developer is opening files to paste content into.

**Artifact naming** — when providing multiple files with the same filename (e.g., multiple `index.blade.php` files), use descriptive artifact names like "Buckets index.blade.php" and "CSV Templates index.blade.php" so they are distinguishable in the download list.

**Only reference existing pages** — do not add navigation links, buttons, or URLs pointing to pages that have not been built yet. Build the pages first, then add the references.

**Input cleaning** — all numeric form inputs should be cleaned server-side to strip commas, currency symbols, and whitespace before validation. Users should be able to type `$50,000.00` or `50,000` without errors.

**Validation errors** — all forms must display validation errors visibly to the user and preserve `old()` input values so they don't have to retype after a failure.

### Schema design conventions

All status and type fields use string columns instead of MySQL ENUMs. ENUMs are difficult to modify in production migrations and cause issues with schema diffing tools. Expected values are documented in this README and enforced in application logic (model constants, validation rules, etc.).

### Composer + PHP 8.5 Compatibility

During initial setup, running `composer create-project` with an older version of Composer on PHP 8.5 produced a `stream_context_create()` TypeError in `RemoteFilesystem::callbackGet()`. The fix was upgrading Composer to v2.9.5+ which has full PHP 8.5 support. See the Composer section under Setup for upgrade instructions.