# EzTaxes

S-Corp tax management dashboard with built-in integrations for **Gusto**, **Coinbase**, **CashApp**, and automatic price lookups via **CryptoCompare** and **Alpha Vantage**. Track your corporate crypto treasury and stock assets. Features an auto-detecting CSV upload page with built-in templates for six supported formats from Gusto, Coinbase, and CashApp - PLUS the ability to upload your own banking records and categorize transactions with regex pattern matching.

**[Live Demo](https://eztaxes.wetfish.net)** — try it with fictional data (read-only)

## Screenshots

### Tax Year Dashboard
Track multiple tax years with income, expenses, and net totals at a glance. Filed years are marked green, drafts in yellow.

![Tax Years Dashboard](docs/screenshots/01-tax-years.png)

### Transaction Categorization
Upload bank CSVs and categorize transactions using regex pattern matching. Bucket groups organize categories by type — Client Income, Operating Expenses, and Ignored transactions are tracked separately.

![Tax Year Detail with Bucket Breakdown](docs/screenshots/02-tax-year-detail.png)

### Payroll with 1120-S Tax Lines
Import Gusto payroll data with automatic officer/employee splitting for IRS form lines. Summary cards map directly to 1120-S Lines 7, 8, 12, and 19.

![Payroll Summary](docs/screenshots/03-payroll-summary.png)

Track US and international contractors with import history showing which Gusto CSV files have been loaded.

![Payroll Contractors and Import History](docs/screenshots/04-payroll-contractors.png)

### Crypto Cost Basis Tracking
Full buy/sell tracking with specific identification, long-term vs short-term classification, and IRS form references. Balance sheet cross-referencing flags discrepancies automatically.

![Crypto Asset Detail](docs/screenshots/05-crypto-detail.png)

### Corporate Balance Sheet
Track crypto, stocks, and cash holdings per tax year. Fetch December 31 prices automatically via CryptoCompare and Alpha Vantage APIs. Crypto holdings cross-reference with the crypto module.

![Balance Sheet](docs/screenshots/06-balance-sheet.png)

## Integrations

**Gusto Payroll** — Import employee payroll, US contractor payments, and international contractor payments directly from Gusto CSV exports. Auto-detects all three report formats, skips preamble rows and summary totals, and routes data into a dedicated Payroll module with 1120-S tax line references (Lines 7, 8, 12, 19). Mark employees as officers to split compensation between Line 7 (Officer Compensation) and Line 8 (Salaries and Wages).

**Coinbase** — Import gain/loss reports with fully-allocated buy+sell pairs. Cost basis, proceeds, and holding period are pre-calculated. Supports the multi-line preamble format Coinbase uses.

**CashApp** — Import transaction history with automatic separation of buys and sells. Sells are queued for manual cost basis allocation using the specific identification method.

**CryptoCompare API** — Free API (no key required) for fetching December 31 year-end crypto prices on the balance sheet.

**Alpha Vantage API** — Free tier (25 calls/day) for fetching December 31 year-end stock prices on the balance sheet. Includes rate limiting with 15-second delays between calls.

**Bank Statements** — Upload CSV exports from any bank or financial institution. Auto-detects common column names (Date, Amount, Description, Posting Date, Memo, etc.) with a priority-based matching system that prefers exact matches over aliases. Save column mappings as reusable templates for repeat imports.

## Smart CSV Upload

One global import page for everything. Upload any CSV and the system automatically detects the format, identifies the tax year from the data, and routes to the correct module:

- Gusto files → Payroll module (employee wages, contractor payments)
- CashApp / Coinbase files → Crypto module (buys, sells, cost basis) with auto-detected asset symbol
- Bank statements → Transaction module (categorization, pattern matching)

Built-in templates are seeded on setup for all six supported formats. The confirmation page shows what was detected with a green banner (e.g., "Detected: Coinbase Gain/Loss") and lets you review before importing. Crypto imports auto-detect the asset and let you select or create one. Bank imports let you adjust the column mapping and save custom templates.

## Features

### Payroll Management
- Three Gusto CSV formats: Employee Payroll, US Contractor Payments, International Contractor Payments
- Officer/employee designation — toggle per person, splits totals between 1120-S Lines 7 and 8
- Per-employee and per-contractor summaries with expandable detail views
- Tax reconciliation entries (negative employer taxes) handled automatically
- 1120-S tax line references: Line 7 (Officer Compensation), Line 8 (Salaries and Wages), Line 12 (Employer Taxes), Line 17 (Employer Contributions), Line 19 (Total Contractors with US/Intl breakdown)
- Summary rows ("Grand totals", "Total Report", "All contractors") automatically filtered out during import

### Transaction Import & Categorization
- Upload bank CSV files with auto-detected column mapping and priority-based header matching
- Preamble scanning — handles CSVs with metadata rows before the actual headers
- Delimiter auto-detection (comma vs tab)
- Regex pattern matching for automatic transaction categorization
- Multi-bucket tagging — a single transaction can belong to multiple categories
- Bucket groups for organizing buckets into categories (Client Income, Operating Expenses, Payroll, Assets, Ignored)
- Default groups seeded automatically on setup
- Tax year detail page shows income/expense subtotals grouped by bucket group
- Pattern builder UI — test a regex against live data, see match count, then save to a bucket
- Manual quick-assign for individual unmatched transactions
- Reusable CSV column mapping templates for repeat imports

### Cryptocurrency Cost Basis
- Track multiple crypto assets (Bitcoin, Ethereum, etc.)
- Record buys and sells manually or import via the global CSV upload page
- Multi-format CSV import with automatic detection: CashApp (creates separate buys/sells) and Coinbase gain/loss reports (creates fully-allocated buy+sell pairs with cost basis pre-calculated)
- Auto-detects asset symbol from CSV data and pre-selects the matching crypto asset, with option to create new assets inline during import
- Smart header scanning — handles CSVs with disclaimer preambles and metadata rows
- Specific identification method — manually select which buys each sell draws from
- Fees tracked on both buys and sells, factored into cost basis and proceeds
- Auto-calculates gain/loss and long-term vs short-term per allocation
- Tax reporting by year with IRS form field references (1099-DA Box 1f / 1g)
- Unallocated sell queue for retroactive cost basis assignment after bulk CSV import
- Balance sheet cross-referencing — shows balance sheet holdings alongside tracked holdings with discrepancy warnings when transactions are missing

### Corporate Balance Sheet
- Track corporate assets (crypto, stocks, cash, other) per tax year
- Automatic Dec 31 price fetching via CryptoCompare (crypto) and Alpha Vantage (stocks)
- Crypto items link to the crypto module for cross-referencing
- Copy balance sheet from previous year with auto-suggested adjustments based on verified crypto buys and sells
- Inline editing for quantity, price, ticker symbol, and notes
- Three independent views (transaction activity, crypto assets, balance sheet) serve as separate sources of truth — data is never merged or double-counted between them

### Artisan Commands
- `taxyear:create {year}` — create a new tax year
- `csv:import {year} {file}` — import a CSV from the command line
- `buckets:import-legacy` — import buckets and patterns from a serialized PHP file
- `report {year}` — generate per-bucket income/expenses summary
- `taxyear:recalculate {year?}` — recalculate cached totals

## Tech Stack

- **Framework:** Laravel 12.12.1 (released March 10, 2026; Laravel 12 initially released February 24, 2025)
- **PHP:** 8.5 (Laravel 12 requires 8.2+; PHP 8.5 compatibility added in Laravel 12.8+)
- **Composer:** 2.9.5+ (older versions are incompatible with PHP 8.5)
- **Database:** MariaDB 12.3, the version [wetfish/server](https://github.com/wetfish/server) runs
- **Frontend:** Blade templates (no starter kit), Tailwind CSS via Vite
- **Environment:** Docker Compose, for development and on servers

## Quick Start (Docker — Development)

Create the env file, install the Composer packages, and generate an app key:

```bash
cp laravel/.env.example laravel/.env
docker compose run --rm --no-deps php composer install
docker compose run --rm --no-deps php php artisan key:generate
```

Build frontend assets (run from host machine):

```bash
cd laravel && npm install && npm run build && cd ..
```

Start the Docker environment:

```bash
docker compose up -d --build
```

Starting runs the migrations and seeders in the `migrate` container before PHP starts. Access the app at `http://localhost:8010`.

## Demo Deployment (wetfish/server)

The live demo runs on [wetfish/server](https://github.com/wetfish/server), which builds this repository's images on the server and deploys them whenever the branch it deploys is pushed. `setup.conf` lists the settings it fills in. On a server running wetfish/server:

```bash
./add-project.sh --name eztaxes --repo https://github.com/wetfish/eztaxes.git --domain eztaxes.wetfish.net --branch main
```

It asks whether to turn on demo mode; answer `true`. Then add the webhook it prints in this repository's settings on GitHub.

Demo mode enables a read-only middleware that blocks all write operations, displays a warning banner on every page, and uses fictional seed data. Set `DEMO_MODE=true` in `.env` to enable.

## Setup

### Environment configuration

`docker-compose.override.yml` sets the local database host, `APP_ENV=local`, and `APP_DEBUG=true`. Real environment variables take precedence over `laravel/.env`, so those values in it have no effect. The rest come from `laravel/.env`, whose database settings match the `db` container:

```ini
DB_DATABASE=eztaxes
DB_USERNAME=eztaxes
DB_PASSWORD=secret
ALPHAVANTAGE_API_KEY=your_key_here
```

From the host machine, the database is accessible on port `3446` (mapped to avoid conflicts with other projects).

### Seeded Data

The `migrate` container runs the DatabaseSeeder each time it starts, which creates:
- Default bucket groups (Client Income, Operating Expenses, Payroll, Assets, Ignored)
- Built-in CSV templates for Gusto Employee Payroll, Gusto US Contractors, Gusto International Contractors, CashApp Crypto, and Coinbase Gain/Loss
- With `DEMO_MODE=true`, fictional demo data, added only while the database has no tax years

The seeders are idempotent and safe to re-run.

### Tailwind CSS

Tailwind is compiled via Vite. No dev server needed — just build on the host:

```bash
cd laravel && npm run build && cd ..
```

Re-run `npm run build` after modifying CSS or Blade templates.

## Docker Environment

`docker-compose.yml` describes EzTaxes as a server runs it, from images the `Dockerfile` builds: the code, Composer packages, and built assets are copied in, and nothing is mounted from the repository. `docker-compose.override.yml`, which Docker Compose reads automatically and servers leave out, adds what local development needs: the code mounted from this checkout, published ports, and a database.

| Service | Image | Purpose | Ports (local) |
|---------|-------|---------|---------------|
| nginx | nginx:1.30.6-alpine (custom) | Serves `laravel/public/`, passes PHP requests to php | 8010 → 80 |
| php | php:8.5.12-fpm (custom) | PHP-FPM with Laravel's extensions and Composer | 9000 (internal) |
| migrate | Same as php | Runs migrations and seeders, then exits; php starts once it has finished | None |
| db | mariadb:12.3.3 | MariaDB database, local only; servers use wetfish/server's shared database | 3446 → 3306 |

| File | Purpose |
|------|---------|
| `Dockerfile` | Builds the php and nginx images, with Composer packages and built assets |
| `docker/nginx/default.conf` | Nginx server block pointing to `laravel/public` |
| `docker/php/custom.ini` | PHP overrides (memory_limit = 512M) |
| `docker/php/entrypoint.sh` | Builds Laravel's caches when a container starts, except in local development |
| `setup.conf` | The settings wetfish/server fills in for `laravel/.env` |

## Documentation

Detailed technical documentation lives in the [`docs/`](docs/) directory:

- [Database Schema](docs/01-database-schema.md) — table definitions, model relationships, cascade behavior
- [Services & Commands](docs/02-services-and-commands.md) — service classes, artisan commands, matching pipeline, crypto calculations
- [Routes & Controllers](docs/03-routes-and-controllers.md) — full route listing, controller responsibilities, request flows
- [Frontend](docs/04-frontend.md) — Tailwind/Vite setup, Blade templates, view structure, UI conventions
- [AI Development Notes](docs/05-ai-development-notes.md) — conventions for AI-assisted development with Claude
- [Planned Features](docs/06-planned-features.md) — future feature roadmap

## Disclaimer

⚠️ EzTaxes is a personal financial tracking tool, not tax preparation software. Use it at your own risk. Always consult a qualified tax professional or financial advisor before filing your taxes. AI tools are great for double-checking your work, but they are not a substitute for professional advice.
