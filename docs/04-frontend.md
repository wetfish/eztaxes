# Frontend

## Tailwind CSS & Vite

Tailwind is compiled via Vite on the host machine — no dev server needed (avoids Docker port conflicts). Run from the `laravel/` directory:

```bash
npm run build
```

Re-run after modifying CSS or Blade templates. There is no `npm run dev` or hot-reload setup.

## Layout

All pages extend `layouts/app.blade.php`, which provides the navigation bar with links to Tax Years, Payroll, Crypto, Buckets, and CSV Templates. The "Import CSV" button is styled as a prominent button on the right side of the nav bar, separate from the regular text links.

## View Directory

```text
laravel/resources/views/
  layouts/
    app.blade.php           → main layout with nav, flash messages, footer
  dashboard/
    index.blade.php         → tax year cards with income/expenses/net, inline create form
  tax-years/
    show.blade.php          → summary cards, expandable bucket table, import history
  transactions/
    index.blade.php         → pattern builder, filter tabs, quick-assign per transaction
  imports/
    upload.blade.php        → global CSV upload page (auto-detect, no tax year needed)
    confirm.blade.php       → format detection banner, tax year picker, column mapping (bank only), preview
  payroll/
    index.blade.php         → tax year cards with officer/employee/contractor/tax summaries
    show.blade.php          → summary cards, 1120-S tax lines, employee table with officer toggle, contractor tables, import history
  buckets/
    index.blade.php         → bucket groups with child buckets, create forms, _bucket-card partial
  csv-templates/
    index.blade.php         → template list with built-in badges, delete for custom only
  crypto/
    index.blade.php         → asset list with holdings, balance sheet cross-reference, add asset form
    show.blade.php          → summary, tax reporting, sells, buys, record buy form
    sell.blade.php          → sell form with scrollable buy allocation table
    allocate.blade.php      → retroactive allocation for unallocated sells
    import.blade.php        → crypto CSV upload (CashApp / Coinbase)
  balance-sheet/
    index.blade.php         → balance sheet items, add form, inline edit, fetch prices button
    copy.blade.php          → copy-from-previous-year preview with adjustments
```

## Global Import Flow

The import page at `/import` is the single entry point for all CSV uploads. The flow:

1. **Upload** — user selects a CSV file. No tax year selection needed upfront.
2. **Detection** — system identifies the format (green banner: "Detected: Gusto Employee Payroll") and the tax year (from preamble or transaction dates).
3. **Confirm** — for payroll imports, just preview and confirm. For bank imports, review/adjust column mapping. Tax year shown in a dropdown with the detected year pre-selected, plus an "Add" option for new years.
4. **Import** — data routes to the correct module. Payroll imports redirect to `/payroll/{year}`, bank imports redirect to `/tax-years/{year}`.

The upload page only shows the custom template dropdown if the user has saved custom templates. Built-in templates are used for auto-detection only and don't appear in the dropdown. Supported formats are listed at the bottom of the upload page.

## UI Conventions

### Navigation

The nav bar shows: EzTaxes (brand), Tax Years, Payroll, Crypto, Buckets, CSV Templates as text links, and "Import CSV" as a styled button (bg-stone-700) on the right.

### Form inputs

All numeric inputs are cleaned server-side with `cleanNumeric()` — strips commas, dollar signs, and whitespace. All forms display validation errors visibly and preserve `old()` input values.

### Detection banners

Detected CSV formats show a green emerald banner with a checkmark icon and the template name. Unrecognized CSVs with preamble show an amber warning noting how many rows were skipped.

### Officer toggle

On the payroll show page, each employee has a clickable role badge in the employee table. "Officer" badges are indigo, "Employee" badges are stone gray. Clicking toggles the status for all entries with that name in the tax year. The toggle immediately recalculates the Line 7/Line 8 split.

### Expandable/collapsible rows

Tax year bucket breakdown: click a table row to toggle visibility of its transactions underneath.

Payroll detail entries: wrapped in `<details>` elements with descriptive summaries (e.g., "Show all 24 individual entries"). Tax reconciliation rows highlighted with amber background, negative amounts in red.

Bucket patterns: each bucket's patterns are in a collapsible section.

Transaction quick-assign: each unmatched transaction has a collapsible bucket assignment form.

Balance sheet inline edit: click an item row to expand an edit form.

### Scrollable tables

Crypto sell and allocate pages use `max-h-64 overflow-y-auto` on the buy allocation table with `sticky top-0` on the thead. Payroll and import preview tables use `overflow-x-auto` for wide CSVs.

### Color coding

Payroll import type badges: blue for Employee, purple for US Contractor, teal for Intl Contractor. Officer badges are indigo.

Crypto sells: unallocated sells appear first with amber highlighting. Gain/loss values: emerald for gains, red for losses.

Balance sheet: asset type badges use purple for crypto, blue for stock, emerald for cash, stone for other.

CSV templates: seeded templates show a "Built-in" stone badge with detection headers listed below.

### Summary cards with tax line references

Payroll summary cards include the 1120-S line number underneath each total (e.g., "1120-S Line 7" under Officer Compensation). The tax line references section shows all relevant lines with amounts, including indented sub-lines for US/Intl contractor breakdown when both exist.

### Page structure patterns

Crypto show page follows a consistent pattern for both sells and buys sections: title → action/form → history table.

Payroll show page: summary cards → tax line references → employee table with officer toggle → US contractors → intl contractors → import history.

Balance sheet and crypto pages use summary cards at the top, then content sections below.