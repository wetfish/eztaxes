# eztaxes

A Laravel 12 dashboard for managing S-Corp business tax history. Built to modernize an existing system of CSV reports and PHP regex-based transaction categorization.

## Project Overview

eztaxes replaces a legacy PHP script that used regex patterns to match bank/statement transaction descriptions against categorized "buckets" (e.g., contractors, server bills, office expenses, rent). The matched data was then used to calculate total income and expenses for tax reporting.

This Laravel project wraps that same logic in a proper web application with a database-backed dashboard, import history, and manual review workflow — while preserving the regex pattern matching system for backwards compatibility with existing data and scripts.

The regex matching system is intended to be phased out over time in favor of a more user-friendly categorization interface.

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
| column_mapping | json | e.g. `{"date": "Posting Date", "amount": "Amount", "description": "Description"}` |
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
| raw_data | json | full original CSV row for reference |
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

### Cascade delete summary

Deleting a **tax year** removes its imports → transactions → bucket_transaction entries.
Deleting an **import** removes its transactions → bucket_transaction entries.
Deleting a **bucket** removes its patterns, schedule lines, and bucket_transaction entries (transactions themselves are preserved).

## Model Relationships

```text
TaxYear          → hasMany Imports, hasMany Transactions
CsvTemplate      → hasMany Imports
Bucket           → hasMany BucketPatterns, hasMany BucketScheduleLines
                   belongsToMany Transactions (pivot: bucket_transaction)
BucketPattern    → belongsTo Bucket, hasMany BucketTransaction
BucketScheduleLine → belongsTo Bucket
Import           → belongsTo TaxYear, belongsTo CsvTemplate
                   hasMany Transactions
Transaction      → belongsTo TaxYear, belongsTo Import
                   belongsToMany Buckets (pivot: bucket_transaction)
```

## Service Classes

### TransactionMatcher (`laravel/app/Services/TransactionMatcher.php`)

Loads all active BucketPatterns ordered by priority. For each transaction description, runs all patterns and returns every matching bucket (not just the first). This supports multi-bucket tagging while preserving priority ordering.

### CsvImporter (`laravel/app/Services/CsvImporter.php`)

Accepts an uploaded CSV file, a TaxYear, and a CsvTemplate (column mapping). Parses rows, creates Transaction records with raw_data preserved, runs each through TransactionMatcher, creates pivot entries, and updates the Import summary counts. Designed to be queue-able for large files.

### TaxYearCalculator (`laravel/app/Services/TaxYearCalculator.php`)

Recalculates totals for a TaxYear by summing distinct transactions (each amount counted once regardless of how many buckets it belongs to). Per-bucket breakdowns show the sum of transactions tagged with that bucket. Called after imports or manual edits.

## Transaction Matching Pipeline

When a CSV is imported:

1. The CSV is parsed using the column mapping from the selected CsvTemplate.
2. The user confirms the detected/selected columns are correct.
3. Each row is saved as a Transaction record with the full row stored in `raw_data`.
4. Each transaction's description is run through all active BucketPatterns, ordered by priority (lowest first).
5. All matching patterns create entries in bucket_transaction with `assigned_via: pattern` and the matching `bucket_pattern_id`.
6. Transactions with at least one match get `match_type: auto`. Those with no matches get `match_type: unmatched`.
7. The Import record is updated with row counts (total, matched, unmatched, ignored).
8. Grand totals on the TaxYear are recalculated from distinct transaction amounts.

## Reporting Notes

When computing grand totals (total income, total expenses), each transaction's amount is counted exactly once — regardless of how many buckets it belongs to. Per-bucket reports sum the transactions tagged with that bucket, which means the bucket-level totals may overlap and won't necessarily add up to the grand total. This is by design and mirrors how the legacy script worked with its sha256 deduplication.

## Routes

```text
GET    /                                → Dashboard (year summary cards)
GET    /tax-years/{year}                → Year detail (bucket breakdown)
GET    /tax-years/{year}/transactions   → Transaction list with filters
POST   /tax-years/{year}/import         → Upload CSV
GET    /buckets                         → Bucket manager
GET    /buckets/{bucket}/patterns       → Pattern manager for a bucket
POST   /buckets/{bucket}/patterns/test  → AJAX test a regex against sample text
GET    /csv-templates                   → CSV template manager
GET    /review/{year}                   → Unmatched transaction review queue
```

## Directory Structure

```text
eztaxes/
  README.md
  Dockerfile
  docker-compose.yml
  docker/
    nginx/        → default.conf
    php/          → custom.ini
  laravel/
    app/
      Models/         → TaxYear, Bucket, BucketPattern, BucketScheduleLine,
                        CsvTemplate, Import, Transaction
      Services/       → TransactionMatcher, CsvImporter, TaxYearCalculator
      Http/
        Controllers/  → DashboardController, TaxYearController,
                        BucketController, PatternController,
                        ImportController, ReviewController,
                        CsvTemplateController
    resources/
      views/
        layouts/      → app.blade.php (main layout w/ nav)
        dashboard/    → index.blade.php
        tax-years/    → show.blade.php, transactions.blade.php
        buckets/      → index.blade.php, patterns.blade.php
        imports/      → create.blade.php, show.blade.php
        csv-templates/ → index.blade.php, create.blade.php
        review/       → index.blade.php
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

## Development Notes

### AI-Assisted Development (Claude)

This project is being built with the assistance of Claude (Anthropic). During initial planning, we identified a rendering issue in the Claude chat interface where consecutive code blocks without explicit language tags (e.g., bare ` ``` ` instead of ` ```bash `) would merge into a single block in the rendered output. The fix is to always use an explicit language identifier on every code block (e.g., `bash`, `ini`, `text`, `php`) and include a descriptive title line before each block.

This note is preserved here so the convention is maintained throughout project documentation.

When referencing files for the developer to open, always provide full file paths relative to the repo root that can be opened directly in VSCode/Codium. For example:

```bash
codium README.md
codium docker/php/custom.ini
```

This convention ensures the developer can copy-paste commands directly without needing to figure out where a file lives.

All Laravel artisan commands should be run via Docker Compose. The `app` container's working directory is already set to the Laravel project root, so artisan can be called directly:

```bash
docker compose exec app php artisan make:migration create_example_table
docker compose exec app php artisan migrate
```

### Schema design conventions

All status and type fields use string columns instead of MySQL ENUMs. ENUMs are difficult to modify in production migrations and cause issues with schema diffing tools. Expected values are documented in this README and enforced in application logic (model constants, validation rules, etc.).

### Composer + PHP 8.5 Compatibility

During initial setup, running `composer create-project` with an older version of Composer on PHP 8.5 produced a `stream_context_create()` TypeError in `RemoteFilesystem::callbackGet()`. The fix was upgrading Composer to v2.9.5+ which has full PHP 8.5 support. See the Composer section under Setup for upgrade instructions.