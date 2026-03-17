# Planned Features

These features are not yet implemented. Documented here for future reference and planning.

## Multi-User Support

Add a `users` table and `user_id` foreign key columns to `tax_years`, `buckets`, and `csv_templates`. Transactions and imports inherit user scoping through their tax_year relationship, so they don't need direct user_id columns. All queries would be scoped by the authenticated user.

## Bucket Hierarchy

Add a nullable `parent_id` (self-referencing FK) to `buckets`. This enables roll-up reporting where child buckets (e.g., "gusto payroll", "gusto tax") aggregate under a parent ("payroll"). Query logic would sum child bucket totals into the parent for reports.

## Schedule Line Management UI

The `bucket_schedule_lines` table exists but there is no UI to add or edit IRS form/line references on buckets yet. When built, this would allow mapping buckets to specific lines on Schedule C, 1099-NEC, and other forms. This data could be used to auto-generate draft form entries from bucket totals.

## Edit Tax Year Status

The tax year `filing_status` field (draft/filed/amended) can only be set at creation. Add a UI control on the tax year detail page to transition status, with confirmation dialogs for marking as filed or amended.

## Edit Bucket Details

Buckets can be created and deleted but not edited (name, behavior, description). Add inline editing or a detail page for modifying bucket properties.

## Asset Price API Integration

The balance sheet currently requires manual entry of Dec 31 asset prices. Potential integrations:

- **CoinGecko** (crypto) — free tier with 10,000 calls/month, historical price endpoint by date
- **Alpha Vantage** (stocks) — free tier with 25 calls/day, daily historical price endpoint

Implementation would add a "Fetch Price" button next to each balance sheet item that suggests a value the user can review and confirm before saving. Prices would never be auto-saved without user confirmation.

## Fixed Asset Depreciation

The balance sheet currently tracks asset values but does not calculate depreciation. When needed, add fields for:

- Date placed in service
- Depreciation method (straight-line, MACRS, Section 179)
- Useful life in years
- Accumulated depreciation

This would feed into IRS Form 4562 (Depreciation and Amortization). The annual depreciation deduction would reduce the asset's book value on each year's balance sheet.

## Balance Sheet Liabilities & Equity

The balance sheet currently tracks assets only. For a complete IRS Schedule L (Form 1120-S), add support for liabilities (accounts payable, loans) and equity (capital stock, retained earnings). The `asset_type` field on `balance_sheet_items` could be extended with a `category` field (asset/liability/equity) or a separate table structure.