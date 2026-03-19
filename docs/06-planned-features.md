# Planned Features

These features are not yet implemented. Documented here for future reference and planning.

## Multi-User Support

Add a `users` table and `user_id` foreign key columns to `tax_years`, `buckets`, and `csv_templates`. Transactions, imports, payroll entries, and balance sheet items inherit user scoping through their tax_year relationship. All queries would be scoped by the authenticated user.

## Schedule Line Management UI

The `bucket_schedule_lines` table exists but there is no UI to add or edit IRS form/line references on buckets yet. When built, this would allow mapping buckets to specific lines on Schedule C, 1099-NEC, and other forms. This data could be used to auto-generate draft form entries from bucket totals.

## Edit Tax Year Status

The tax year `filing_status` field (draft/filed/amended) can only be set at creation. Add a UI control on the tax year detail page to transition status, with confirmation dialogs for marking as filed or amended.

## Edit Bucket Details

Buckets can be created and deleted but not edited (name, behavior, description). Add inline editing or a detail page for modifying bucket properties.

## Fixed Asset Depreciation

The balance sheet currently tracks asset values but does not calculate depreciation. When needed, add fields for: date placed in service, depreciation method (straight-line, MACRS, Section 179), useful life in years, and accumulated depreciation. This would feed into IRS Form 4562 (Depreciation and Amortization).

## Balance Sheet Liabilities & Equity

The balance sheet currently tracks assets only. For a complete IRS Schedule L (Form 1120-S), add support for liabilities (accounts payable, loans) and equity (capital stock, retained earnings). The `asset_type` field on `balance_sheet_items` could be extended with a `category` field (asset/liability/equity) or a separate table structure.

## 1120-S Form Generation

Aggregate data from all three modules (bank transactions, payroll, crypto) into a consolidated 1120-S summary view. Map bucket totals to their schedule lines, combine with payroll tax line references and crypto capital gains data, and generate a printable/exportable summary matching the 1120-S form layout.

## Payroll Officer Persistence Across Imports

Currently, officer designation is set per-employee-name per-tax-year. When a new payroll CSV is imported, new entries for an already-designated officer default to `is_officer: false`. Consider auto-detecting officer status from previous imports for the same name within the same tax year.