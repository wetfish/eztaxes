# AI Development Notes

This project is being built with the assistance of Claude (Anthropic). The following conventions must be maintained for consistent, high-quality output. These notes serve as a reference for both the AI assistant and the developer.

## Code Block Formatting

Always use an explicit language identifier on every code block (e.g., `bash`, `ini`, `text`, `php`). Include a descriptive title line before each block.

Bare ` ``` ` without a language tag causes consecutive blocks to merge into a single block in the Claude chat renderer. This was identified early in the project and must be avoided throughout all documentation and chat output.

## File Path References

When referencing files for the developer to open, always provide full file paths relative to the repo root using `codium` commands:

```bash
codium README.md
codium docker/php/custom.ini
codium laravel/app/Http/Controllers/CryptoController.php
```

This convention ensures the developer can copy-paste commands directly without needing to figure out where a file lives.

## Artifact Ordering

When providing downloadable file artifacts alongside a list of `codium` commands, the `codium` commands must be listed in the same order as the artifacts appear in the download list. This prevents confusion when the developer is opening files sequentially to paste content into.

For example, if three artifacts are provided in order — Controller, View, Routes — the commands should be:

```bash
codium laravel/app/Http/Controllers/ExampleController.php
codium laravel/resources/views/example/index.blade.php
codium laravel/routes/web.php
```

## Artifact Naming

When providing multiple files with the same filename (e.g., multiple `index.blade.php` files), use descriptive artifact names so they are distinguishable in the download list. For example: "Buckets index.blade" and "CSV Templates index.blade" rather than two files both named "index.blade".

## Artisan Commands

All Laravel artisan commands should be run via Docker Compose. The `app` container's working directory is already set to the Laravel project root:

```bash
docker compose exec app php artisan make:migration create_example_table
docker compose exec app php artisan migrate
```

## Migration Generation

When generating multiple migrations in sequence, add `sleep 1` between commands to ensure unique timestamps. MySQL requires referenced tables to exist before foreign keys point to them, and alphabetical ordering of same-timestamp migrations can cause failures:

```bash
docker compose exec app php artisan make:migration create_first_table
sleep 1
docker compose exec app php artisan make:migration create_second_table
```

## Page References

Do not add navigation links, buttons, or URLs pointing to pages that have not been built yet. Build the pages first, then add the references. This avoids broken links and confusion during development.

## Input Cleaning

All numeric form inputs should be cleaned server-side to strip commas, currency symbols, and whitespace before validation. The `cleanNumeric()` helper handles this. Users should be able to type `$50,000.00` or `50,000` without errors.

## Validation Errors

All forms must display validation errors visibly to the user (typically in red text above or within the form) and preserve `old()` input values so users don't have to retype after a failure.

## Schema Conventions

All status and type fields use string columns instead of MySQL ENUMs. ENUMs are difficult to modify in production migrations and cause issues with schema diffing tools. Expected values are documented in the schema docs and enforced in application logic.

## Privacy

Avoid committing real financial institution names or personal data to the repository. References to specific banks should use generic names (e.g., "Local Credit Union" instead of the actual institution name). If sensitive data is accidentally committed, use `git filter-repo --replace-text` to rewrite history.

## Data Separation

Bank transactions, payroll, and crypto are three independent modules with separate database tables. Data is NEVER merged or double-counted between them. Gusto payroll data goes exclusively into the Payroll module — it should never be imported as bank transactions. If Gusto line items appear in a bank statement CSV (e.g., "GUSTO TYPE: NET"), they should be assigned to an "Ignored" bucket so they don't inflate expense totals, since the Payroll module already tracks those amounts with full detail. Similarly, crypto capital gains are tracked in the Crypto module and are not added to bank transaction totals.

## Import Module Routing

All CSV imports go through the global ImportController at `/import`. The controller uses seeded CSV template detection to route files to the correct module. When adding a new integration, add a seeded CsvTemplate with `detection_headers` and add the template name to `ImportController::TEMPLATE_ROUTES`.

## Column Detection Priority

When auto-detecting CSV columns, use a two-pass priority system: preferred/exact matches first (e.g., "description"), then alias fallbacks (e.g., "memo"). This prevents situations where an alias column is chosen over an exact match that appears earlier in the CSV. Use `findBestColumn()` in ImportController.