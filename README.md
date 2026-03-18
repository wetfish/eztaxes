# EzTaxes

A Laravel 12 dashboard for managing S-Corp business tax history, cryptocurrency cost basis tracking, and corporate balance sheets. Built to modernize an existing system of CSV reports and PHP regex-based transaction categorization.

## Features

### Transaction Import & Categorization
- Upload bank CSV files with auto-detected column mapping
- Regex pattern matching for automatic transaction categorization
- Multi-bucket tagging — a single transaction can belong to multiple categories
- Pattern builder UI — test a regex against live data, see match count, then save to a bucket
- Manual quick-assign for individual unmatched transactions
- Reusable CSV column mapping templates for repeat imports

### Cryptocurrency Cost Basis
- Track multiple crypto assets (Bitcoin, Ethereum, etc.)
- Record buys and sells manually or import via CSV (e.g., CashApp format)
- Specific identification method — manually select which buys each sell draws from
- Fees tracked on both buys and sells, factored into cost basis and proceeds
- Auto-calculates gain/loss and long-term vs short-term per allocation
- Tax reporting by year with IRS form field references (1099-DA Box 1f / 1g)
- Unallocated sell queue for retroactive cost basis assignment after bulk CSV import

### Corporate Balance Sheet
- Track corporate assets (crypto, stocks, cash, other) per tax year
- Enter quantities and December 31 year-end prices
- Crypto items link to the crypto module — shows tracked buy/sell activity as hints
- Copy balance sheet from previous year with auto-suggested adjustments based on verified crypto activity
- Inline editing for quantity, price, and notes

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
- **Database:** MySQL 8.0
- **Frontend:** Blade templates (no starter kit), Tailwind CSS via Vite
- **Environment:** Docker (PHP-FPM, Nginx, MySQL, Node for asset builds)

## Quick Start

Start the Docker environment:

```bash
docker compose up -d
```

Run migrations:

```bash
docker compose exec app php artisan migrate
```

Build frontend assets (run from host machine):

```bash
cd laravel && npm install && npm run build
```

Access the app at `http://localhost:8010`.

## Setup

### Environment configuration

Update `laravel/.env` to match the Docker container names:

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

Laravel 12 requires the following PHP extensions: Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, Session, Tokenizer, and XML. Several of these are bundled with `php8.5-common`.

Install all required dependencies on Ubuntu/Debian:

```bash
sudo apt install php8.5-cli php8.5-common php8.5-curl php8.5-mbstring php8.5-xml php8.5-bcmath php8.5-zip php8.5-mysql php8.5-fpm unzip -y
```

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

Composer 2.9.5+ is required. Older versions have a known incompatibility with PHP 8.5 (`stream_context_create()` error in `RemoteFilesystem`). Upgrade with:

```bash
composer self-update
```

If self-update fails, do a fresh install:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo mv composer.phar /usr/local/bin/composer
```

### Tailwind CSS

Tailwind is compiled via Vite. No dev server needed — just build on the host:

```bash
cd laravel && npm run build
```

Re-run `npm run build` after modifying CSS or Blade templates.

### Migrations

The default Laravel migrations (users, password_reset_tokens, sessions, cache, jobs) have been removed. This project uses a custom schema with file-based sessions (`SESSION_DRIVER=file`).

```bash
docker compose exec app php artisan migrate
```

## Docker Environment

| Container | Image | Purpose | Ports |
|-----------|-------|---------|-------|
| eztaxes-app | php:8.5-fpm (custom) | PHP-FPM with Laravel extensions, Composer, Redis | 9000 (internal) |
| eztaxes-nginx | nginx:alpine | Serves `laravel/public/`, proxies PHP to app | 8010 → 80 |
| eztaxes-db | mysql:8.0 | MySQL database | 3446 → 3306 |

| Config File | Purpose |
|-------------|---------|
| `docker/nginx/default.conf` | Nginx server block pointing to `laravel/public` |
| `docker/php/custom.ini` | PHP overrides (memory_limit = 512M) |

## Documentation

Detailed technical documentation lives in the [`docs/`](docs/) directory:

- [Database Schema](docs/01-database-schema.md) — table definitions, model relationships, cascade behavior
- [Services & Commands](docs/02-services-and-commands.md) — service classes, artisan commands, matching pipeline, crypto calculations
- [Routes & Controllers](docs/03-routes-and-controllers.md) — full route listing, controller responsibilities, request flows
- [Frontend](docs/04-frontend.md) — Tailwind/Vite setup, Blade templates, view structure, UI conventions
- [AI Development Notes](docs/05-ai-development-notes.md) — conventions for AI-assisted development with Claude
- [Planned Features](docs/06-planned-features.md) — future feature roadmap