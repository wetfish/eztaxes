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

### Create the project

```bash
composer create-project laravel/laravel:^12.0 eztaxes
cd eztaxes
```

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

## Data Model

### tax_years

The top-level container. Each record represents one filing year.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| year | int | unique, e.g. 2024 |
| filing_status | enum | draft / filed / amended |
| total_income | decimal | computed from transactions |
| total_expenses | decimal | computed from transactions |
| notes | text | nullable |
| timestamps | | created_at, updated_at |

### buckets

Categorization targets that persist across years.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Server Bills" |
| slug | string | e.g. "server-bills" |
| type | enum | income / expense |
| schedule_line | string | nullable, e.g. "Schedule C - Line 17" |
| description | text | nullable |
| is_active | boolean | |
| sort_order | int | |
| timestamps | | created_at, updated_at |

### bucket_patterns

Regex rules for automatic transaction matching. Each pattern belongs to a bucket.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_id | foreign | references buckets |
| pattern | string | the regex |
| priority | int | lower = matched first |
| description | string | nullable, human note like "matches AWS invoices" |
| is_active | boolean | |
| timestamps | | created_at, updated_at |

### imports

Log of each CSV file uploaded.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | foreign | references tax_years |
| filename | string | stored filename |
| original_filename | string | as uploaded |
| rows_total | int | |
| rows_matched | int | |
| rows_unmatched | int | |
| imported_at | timestamp | |
| timestamps | | created_at, updated_at |

### transactions

Individual line items imported from CSVs.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | foreign | references tax_years |
| import_id | foreign | nullable, references imports |
| bucket_id | foreign | nullable, references buckets |
| date | date | transaction date |
| description | string | raw text from bank/statement |
| amount | decimal(10,2) | |
| match_type | enum | auto / manual / unmatched |
| source_reference | string | nullable, check number or ref ID |
| timestamps | | created_at, updated_at |

Unique composite index on `[tax_year_id, date, description, amount]` to prevent duplicate imports.

## Model Relationships

```text
TaxYear       → hasMany Transactions, hasMany Imports
Bucket        → hasMany BucketPatterns, hasMany Transactions
BucketPattern → belongsTo Bucket
Transaction   → belongsTo TaxYear, belongsTo Bucket, belongsTo Import
Import        → belongsTo TaxYear, hasMany Transactions
```

## Service Classes

### TransactionMatcher (`app/Services/TransactionMatcher.php`)

Accepts a transaction description string. Loads all active BucketPatterns ordered by priority. Runs each regex against the description and returns the first matching Bucket (or null). This is the direct equivalent of the legacy PHP script's core logic.

### CsvImporter (`app/Services/CsvImporter.php`)

Accepts an uploaded CSV file and a TaxYear. Parses rows, creates Transaction records, runs each through TransactionMatcher, links matches, and creates the Import summary record. Designed to be queue-able for large files.

### TaxYearCalculator (`app/Services/TaxYearCalculator.php`)

Recalculates totals for a TaxYear by summing transactions grouped by bucket type. Called after imports or manual edits.

## Transaction Matching Pipeline

When a CSV is imported:

1. Each row is parsed into a Transaction record.
2. The transaction's description is run through all active BucketPatterns, ordered by priority (lowest first).
3. The first regex match wins — the transaction is linked to that pattern's bucket with `match_type: auto`.
4. Unmatched transactions are saved with `match_type: unmatched` and flagged for manual review on the dashboard.

## Routes

```text
GET    /                                → Dashboard (year summary cards)
GET    /tax-years/{year}                → Year detail (bucket breakdown)
GET    /tax-years/{year}/transactions   → Transaction list with filters
POST   /tax-years/{year}/import         → Upload CSV
GET    /buckets                         → Bucket manager
GET    /buckets/{bucket}/patterns       → Pattern manager for a bucket
POST   /buckets/{bucket}/patterns/test  → AJAX test a regex against sample text
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
      Models/         → TaxYear, Bucket, BucketPattern, Transaction, Import
      Services/       → TransactionMatcher, CsvImporter, TaxYearCalculator
      Http/
        Controllers/  → DashboardController, TaxYearController,
                        BucketController, PatternController,
                        ImportController, ReviewController
    resources/
      views/
        layouts/      → app.blade.php (main layout w/ nav)
        dashboard/    → index.blade.php
        tax-years/    → show.blade.php, transactions.blade.php
        buckets/      → index.blade.php, patterns.blade.php
        imports/      → create.blade.php, show.blade.php
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

### Composer + PHP 8.5 Compatibility

During initial setup, running `composer create-project` with an older version of Composer on PHP 8.5 produced a `stream_context_create()` TypeError in `RemoteFilesystem::callbackGet()`. The fix was upgrading Composer to v2.9.5+ which has full PHP 8.5 support. See the Composer section under Setup for upgrade instructions.