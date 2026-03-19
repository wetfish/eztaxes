# Database Schema

All status and type fields use string columns rather than MySQL ENUMs. ENUMs are difficult to modify in production migrations and cause issues with schema diffing tools. Expected values are documented below and enforced in application logic.

## Transaction Categorization Tables

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

Reusable column mappings for different CSV sources. Seeded templates provide auto-detection for known formats (Gusto, etc.). Custom templates can be saved during bank CSV imports.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Gusto Employee Payroll", "Local Credit Union Checking" |
| column_mapping | json | e.g. `{"date": 0, "amount": 2, "description": 1}` or header-name-based for seeded templates |
| detection_headers | json | nullable. Array of header names that must all be present to auto-detect this format |
| is_seeded | boolean | default false. Built-in templates cannot be deleted |
| timestamps | | created_at, updated_at |

Seeded templates use header-name-based column mapping (e.g., `{"amount_header": "Check amount", "description_header": "Employee"}`) which is resolved to column indices at runtime. Custom templates use direct column indices.

### bucket_groups

Organizational containers for grouping related buckets. Groups are separate from buckets — they don't have patterns or behaviors. Default groups are seeded automatically: Client Income, Operating Expenses, Payroll, Assets, Ignored.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Client Income", "Operating Expenses" |
| slug | string | unique, e.g. "client-income" |
| sort_order | int | default 0 |
| timestamps | | created_at, updated_at |

### buckets

Categorization targets. A bucket groups related transactions (e.g., "contractors", "servers", "food"). Buckets are global — they apply across all tax years. Each bucket can optionally belong to a bucket group.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_group_id | FK | nullable, references bucket_groups, null on delete |
| name | string | e.g. "Server Bills" |
| slug | string | unique, e.g. "server-bills" |
| behavior | string | expected: `normal`, `ignored`, `informational` |
| description | text | nullable |
| sort_order | int | default 0 |
| is_active | boolean | default true |
| timestamps | | created_at, updated_at |

Behavior values:
- `normal` — included in income/expense totals
- `ignored` — transactions are categorized but excluded from totals (internal transfers, verification deposits)
- `informational` — tracked for reference but not counted

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

Maps buckets to IRS form/line references. A single bucket can appear on multiple forms.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_id | FK | references buckets, cascade on delete |
| form_name | string | e.g. "Schedule C", "1099-NEC" |
| line_reference | string | e.g. "Line 11", "Box 1" |
| description | string | nullable, e.g. "Contract Labor" |
| timestamps | | created_at, updated_at |

### imports

Record of each CSV file uploaded. Deleting an import cascades to remove all associated transactions.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | FK | references tax_years, cascade on delete |
| csv_template_id | FK | nullable, references csv_templates, null on delete |
| original_filename | string | as uploaded |
| rows_total | int | default 0 |
| rows_matched | int | default 0 |
| rows_unmatched | int | default 0 |
| rows_ignored | int | default 0 |
| imported_at | timestamp | |
| timestamps | | created_at, updated_at |

### transactions

Individual line items parsed from CSVs. The amount is signed: positive = income, negative = expense.

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

Links transactions to buckets. A transaction can belong to multiple buckets.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| bucket_id | FK | references buckets, cascade on delete |
| transaction_id | FK | references transactions, cascade on delete |
| assigned_via | string | expected: `pattern`, `manual` |
| bucket_pattern_id | FK | nullable, references bucket_patterns, null on delete |
| timestamps | | created_at, updated_at |

Unique index on `[bucket_id, transaction_id]` to prevent duplicate assignments.

## Cryptocurrency Tables

### crypto_assets

Cryptocurrency assets tracked in the system.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| name | string | e.g. "Bitcoin" |
| symbol | string | unique, e.g. "BTC" |
| timestamps | | created_at, updated_at |

### crypto_buys

Individual purchase transactions. `quantity_remaining` decreases as sells are allocated against this buy.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| crypto_asset_id | FK | references crypto_assets, cascade on delete |
| date | date | purchase date |
| quantity | decimal(16,8) | amount purchased |
| cost_per_unit | decimal(16,2) | price paid per unit |
| total_cost | decimal(12,2) | (quantity × cost_per_unit) + fee |
| fee | decimal(10,2) | transaction fee, increases cost basis |
| quantity_remaining | decimal(16,8) | decreases as sells reference this buy |
| notes | text | nullable |
| timestamps | | created_at, updated_at |

### crypto_sells

Individual sale transactions. Cost basis and gain/loss are computed from buy allocations.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| crypto_asset_id | FK | references crypto_assets, cascade on delete |
| date | date | sale date |
| quantity | decimal(16,8) | amount sold |
| price_per_unit | decimal(16,2) | price received per unit |
| total_proceeds | decimal(12,2) | (quantity × price_per_unit) - fee |
| fee | decimal(10,2) | transaction fee, reduces proceeds |
| total_cost_basis | decimal(12,2) | sum from allocated buys |
| gain_loss | decimal(12,2) | total_proceeds - total_cost_basis |
| notes | text | nullable |
| timestamps | | created_at, updated_at |

### crypto_buy_sell (pivot)

Links sells to the specific buys they draw from (specific identification method).

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

## Balance Sheet Tables

### balance_sheet_items

Corporate balance sheet line items tracked per tax year. Crypto items can optionally link to the crypto module for activity hints.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | FK | references tax_years, cascade on delete |
| crypto_asset_id | FK | nullable, references crypto_assets, null on delete |
| label | string | e.g. "Bitcoin", "Business Checking", "AAPL Stock" |
| asset_type | string | expected: `crypto`, `stock`, `cash`, `other` |
| quantity | decimal(16,8) | nullable, for countable assets like crypto/stocks |
| unit_price_year_end | decimal(16,2) | nullable, Dec 31 price per unit |
| total_value | decimal(12,2) | quantity × unit_price, or manual entry for cash |
| notes | text | nullable |
| sort_order | int | default 0 |
| timestamps | | created_at, updated_at |

## Payroll Tables

### payroll_imports

Import records for Gusto CSV files, similar to `imports` but for payroll data.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | bigint | FK → tax_years, cascade delete |
| csv_template_id | bigint | nullable FK → csv_templates, null on delete |
| type | string | `employee`, `us_contractor`, `intl_contractor` |
| original_filename | string | |
| rows_total | int | default 0 |
| imported_at | timestamp | nullable |
| timestamps | | created_at, updated_at |

### payroll_entries

Individual payroll line items. One table for all three Gusto report types — type-specific fields are nullable.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint | primary key |
| tax_year_id | bigint | FK → tax_years, cascade delete |
| payroll_import_id | bigint | FK → payroll_imports, cascade delete |
| type | string | `employee`, `us_contractor`, `intl_contractor` |
| name | string | employee name or contractor name |
| is_officer | boolean | default false. Officer pay → 1120-S Line 7, employee pay → Line 8 |
| date | date | nullable. Dec 31 fallback for US contractors |
| gross_pay | decimal(10,2) | Gross earnings (employee), Total Amount (US contractor), USD amount (intl) |
| employee_deductions | decimal(10,2) | employee type only |
| employer_contributions | decimal(10,2) | employee type only |
| employee_taxes | decimal(10,2) | employee type only |
| employer_taxes | decimal(10,2) | employee type only |
| net_pay | decimal(10,2) | employee type only |
| employer_cost | decimal(10,2) | employee type only |
| check_amount | decimal(10,2) | employee type only |
| wage_type | string | nullable, intl contractor only |
| currency | string | nullable, intl contractor only |
| foreign_amount | decimal(10,2) | nullable, intl contractor only |
| payment_status | string | nullable, intl contractor only |
| hours | decimal(8,2) | nullable, intl contractor only |
| hourly_rate | decimal(8,2) | nullable, intl contractor only |
| department | string | nullable, US contractor only |
| tips_payment | decimal(10,2) | nullable, US contractor only |
| tips_cash | decimal(10,2) | nullable, US contractor only |
| notes | string | nullable. Pay period info for employees, memo for intl contractors |
| timestamps | | created_at, updated_at |

## Model Relationships

```text
TaxYear            → hasMany Imports, hasMany Transactions, hasMany BalanceSheetItems
                     hasMany PayrollImports, hasMany PayrollEntries
CsvTemplate        → hasMany Imports, hasMany PayrollImports
BucketGroup        → hasMany Buckets
Bucket             → belongsTo BucketGroup (nullable)
                     hasMany BucketPatterns, hasMany BucketScheduleLines
                     belongsToMany Transactions (pivot: bucket_transaction)
BucketPattern      → belongsTo Bucket
BucketScheduleLine → belongsTo Bucket
Import             → belongsTo TaxYear, belongsTo CsvTemplate, hasMany Transactions
Transaction        → belongsTo TaxYear, belongsTo Import
                     belongsToMany Buckets (pivot: bucket_transaction)
PayrollImport      → belongsTo TaxYear, belongsTo CsvTemplate, hasMany PayrollEntries
PayrollEntry       → belongsTo TaxYear, belongsTo PayrollImport
CryptoAsset        → hasMany CryptoBuys, hasMany CryptoSells
CryptoBuy          → belongsTo CryptoAsset
                     belongsToMany CryptoSells (pivot: crypto_buy_sell)
CryptoSell         → belongsTo CryptoAsset
                     belongsToMany CryptoBuys (pivot: crypto_buy_sell)
BalanceSheetItem   → belongsTo TaxYear, belongsTo CryptoAsset (nullable)
```

## Cascade Delete Behavior

Deleting a **tax year** removes its imports → transactions → bucket_transaction entries, and its balance sheet items.

Deleting an **import** removes its transactions → bucket_transaction entries.

Deleting a **bucket group** unassigns all its buckets (sets `bucket_group_id` to null). Buckets and their patterns are preserved.

Deleting a **bucket** removes its patterns, schedule lines, and bucket_transaction entries. Transactions themselves are preserved.

Deleting a **crypto asset** removes its buys → crypto_buy_sell entries, its sells → crypto_buy_sell entries, and nullifies linked balance sheet items.

Deleting a **crypto sell** restores the allocated quantities on the referenced buys.

Deleting a **payroll import** removes its payroll entries.

Deleting a **tax year** also removes its payroll imports → payroll entries.