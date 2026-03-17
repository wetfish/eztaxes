# Frontend

## Tailwind CSS & Vite

Tailwind is compiled via Vite on the host machine — no dev server needed (avoids Docker port conflicts). Run from the `laravel/` directory:

```bash
npm run build
```

Re-run after modifying CSS or Blade templates. There is no `npm run dev` or hot-reload setup.

## Layout

All pages extend `layouts/app.blade.php`, which provides the navigation bar with links to Dashboard, Crypto, Buckets, and CSV Templates.

## View Directory

```text
laravel/resources/views/
  layouts/
    app.blade.php           → main layout with nav
  dashboard/
    index.blade.php         → tax year cards with income/expenses/net, inline create form
  tax-years/
    show.blade.php          → summary cards, expandable bucket table, import history
  transactions/
    index.blade.php         → pattern builder, filter tabs, quick-assign per transaction
  imports/
    upload.blade.php        → CSV file upload with column detection
    confirm.blade.php       → column mapping confirmation with preview
  buckets/
    index.blade.php         → bucket list with patterns, create form
  csv-templates/
    index.blade.php         → template list with delete
  crypto/
    index.blade.php         → asset list with holdings, add asset form
    show.blade.php          → summary, tax reporting, sells, buys, record buy form
    sell.blade.php          → sell form with scrollable buy allocation table
    allocate.blade.php      → retroactive allocation for unallocated sells
    import.blade.php        → crypto CSV upload
  balance-sheet/
    index.blade.php         → balance sheet items, add form, inline edit
    copy.blade.php          → copy-from-previous-year preview with adjustments
```

## UI Conventions

### Form inputs

All numeric inputs are cleaned server-side with `cleanNumeric()` — strips commas, dollar signs, and whitespace. Users can type `$50,000.00` or `50,000` without errors.

All forms display validation errors visibly and preserve `old()` input values so users don't have to retype after a validation failure.

### Dynamic form fields

The balance sheet "Add Asset" form dynamically shows/hides fields based on the selected asset type. Crypto and stock types show quantity + Dec 31 price fields. Cash and other types show a single total value field.

### Expandable/collapsible rows

Tax year bucket breakdown: click a table row to toggle visibility of its transactions underneath.

Bucket patterns: each bucket's patterns are in a collapsible section.

Transaction quick-assign: each unmatched transaction has a collapsible bucket assignment form.

Balance sheet inline edit: click an item row to expand an edit form.

Row expansion uses alternating backgrounds (`bg-stone-50`/`bg-white`) with `$loop->index` for visual distinction.

### Scrollable tables

Crypto sell and allocate pages use `max-h-64 overflow-y-auto` on the buy allocation table with `sticky top-0` on the thead. This keeps the table compact and ensures the Save button remains visible without scrolling.

### Color coding

Crypto sells: unallocated sells appear first in the table with amber highlighting. Allocated sells are expandable to show their buy allocations.

Balance sheet: asset type badges use purple for crypto, blue for stock, emerald for cash, stone for other. Crypto activity hints appear in purple info rows.

Gain/loss values: emerald for gains, red for losses.

### Page structure patterns

Crypto show page follows a consistent pattern for both sells and buys sections: title → action/form → history table. The "Record Sell" button is next to the sells title, and the "Record Buy" form sits between the buys title and buys table.

Balance sheet and crypto pages use summary cards at the top, then content sections below.