<?php

namespace Database\Seeders;

use App\Models\BalanceSheetItem;
use App\Models\Bucket;
use App\Models\BucketGroup;
use App\Models\BucketPattern;
use App\Models\CryptoAsset;
use App\Models\CryptoBuy;
use App\Models\CryptoSell;
use App\Models\Import;
use App\Models\PayrollEntry;
use App\Models\PayrollImport;
use App\Models\TaxYear;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding demo data for Pixel Forge Studios...');

        // ─── Tax Years ───
        $ty2023 = TaxYear::create(['year' => 2023, 'filing_status' => 'filed', 'total_income' => 0, 'total_expenses' => 0]);
        $ty2024 = TaxYear::create(['year' => 2024, 'filing_status' => 'filed', 'total_income' => 0, 'total_expenses' => 0]);
        $ty2025 = TaxYear::create(['year' => 2025, 'filing_status' => 'draft', 'total_income' => 0, 'total_expenses' => 0]);

        // ─── Buckets & Patterns ───
        $this->seedBuckets();

        // ─── Bank Transactions ───
        $this->seedTransactions($ty2023, $ty2024, $ty2025);

        // ─── Payroll ───
        $this->seedPayroll($ty2023, $ty2024, $ty2025);

        // ─── Crypto ───
        $this->seedCrypto();

        // ─── Balance Sheets ───
        $this->seedBalanceSheets($ty2023, $ty2024, $ty2025);

        // ─── Recalculate Totals ───
        $calculator = app(\App\Services\TaxYearCalculator::class);
        $calculator->recalculate($ty2023);
        $calculator->recalculate($ty2024);
        $calculator->recalculate($ty2025);

        $this->command->info('Demo data seeded successfully!');
    }

    private function seedBuckets(): void
    {
        $incomeGroup = BucketGroup::where('slug', 'client-income')->first();
        $expenseGroup = BucketGroup::where('slug', 'operating-expenses')->first();
        $ignoredGroup = BucketGroup::where('slug', 'ignored')->first();

        // Income buckets
        $acme = Bucket::create(['bucket_group_id' => $incomeGroup->id, 'name' => 'Acme Corp', 'slug' => 'acme-corp', 'behavior' => 'normal', 'sort_order' => 1, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $acme->id, 'pattern' => 'ACME CORP', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $acme->id, 'pattern' => 'ACME.*PAYMENT', 'priority' => 10, 'is_active' => true]);

        $brightpath = Bucket::create(['bucket_group_id' => $incomeGroup->id, 'name' => 'Brightpath Digital', 'slug' => 'brightpath', 'behavior' => 'normal', 'sort_order' => 2, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $brightpath->id, 'pattern' => 'BRIGHTPATH', 'priority' => 10, 'is_active' => true]);

        $cascade = Bucket::create(['bucket_group_id' => $incomeGroup->id, 'name' => 'Cascade Solutions', 'slug' => 'cascade', 'behavior' => 'normal', 'sort_order' => 3, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $cascade->id, 'pattern' => 'CASCADE SOL', 'priority' => 10, 'is_active' => true]);

        // Expense buckets
        $hosting = Bucket::create(['bucket_group_id' => $expenseGroup->id, 'name' => 'Hosting & Infrastructure', 'slug' => 'hosting', 'behavior' => 'normal', 'sort_order' => 1, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $hosting->id, 'pattern' => 'AWS.*SERVICES', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $hosting->id, 'pattern' => 'DIGITALOCEAN', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $hosting->id, 'pattern' => 'CLOUDFLARE', 'priority' => 10, 'is_active' => true]);

        $software = Bucket::create(['bucket_group_id' => $expenseGroup->id, 'name' => 'Software Subscriptions', 'slug' => 'software', 'behavior' => 'normal', 'sort_order' => 2, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $software->id, 'pattern' => 'GITHUB', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $software->id, 'pattern' => 'ADOBE', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $software->id, 'pattern' => 'SLACK', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $software->id, 'pattern' => 'FIGMA', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $software->id, 'pattern' => 'JETBRAINS', 'priority' => 10, 'is_active' => true]);

        $office = Bucket::create(['bucket_group_id' => $expenseGroup->id, 'name' => 'Office & Equipment', 'slug' => 'office', 'behavior' => 'normal', 'sort_order' => 3, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $office->id, 'pattern' => 'AMAZON.*MARKETPLACE', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $office->id, 'pattern' => 'BEST BUY', 'priority' => 10, 'is_active' => true]);

        $insurance = Bucket::create(['bucket_group_id' => $expenseGroup->id, 'name' => 'Business Insurance', 'slug' => 'insurance', 'behavior' => 'normal', 'sort_order' => 4, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $insurance->id, 'pattern' => 'HISCOX', 'priority' => 10, 'is_active' => true]);

        $professional = Bucket::create(['bucket_group_id' => $expenseGroup->id, 'name' => 'Professional Services', 'slug' => 'professional', 'behavior' => 'normal', 'sort_order' => 5, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $professional->id, 'pattern' => 'TURBOTAX', 'priority' => 10, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $professional->id, 'pattern' => 'REGISTERED AGENT', 'priority' => 10, 'is_active' => true]);

        // Ignored buckets
        $transfers = Bucket::create(['bucket_group_id' => $ignoredGroup->id, 'name' => 'Internal Transfers', 'slug' => 'transfers', 'behavior' => 'ignored', 'sort_order' => 1, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $transfers->id, 'pattern' => 'TRANSFER.*SAVINGS', 'priority' => 5, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $transfers->id, 'pattern' => 'ONLINE TRANSFER', 'priority' => 5, 'is_active' => true]);

        $gusto = Bucket::create(['bucket_group_id' => $ignoredGroup->id, 'name' => 'Gusto (see Payroll)', 'slug' => 'gusto-ignored', 'behavior' => 'ignored', 'sort_order' => 2, 'is_active' => true]);
        BucketPattern::create(['bucket_id' => $gusto->id, 'pattern' => 'GUSTO', 'priority' => 5, 'is_active' => true]);
    }

    private function seedTransactions(TaxYear $ty2023, TaxYear $ty2024, TaxYear $ty2025): void
    {
        $matcher = app(\App\Services\TransactionMatcher::class);

        foreach ([$ty2023, $ty2024, $ty2025] as $taxYear) {
            $year = $taxYear->year;
            $multiplier = match ($year) { 2023 => 0.8, 2024 => 1.0, 2025 => 1.2 };

            $import = Import::create([
                'tax_year_id' => $taxYear->id,
                'original_filename' => "demo-bank-{$year}.csv",
                'imported_at' => now(),
            ]);

            $rows = $this->getBankTransactions($year, $multiplier);
            $matched = 0;
            $unmatched = 0;
            $ignored = 0;

            $ignoredBucketIds = Bucket::where('behavior', 'ignored')->pluck('id')->toArray();

            foreach ($rows as $row) {
                $matches = $matcher->match($row['description']);

                $matchType = empty($matches) ? 'unmatched' : 'auto';
                $isIgnored = false;

                if (!empty($matches)) {
                    $matchedBucketIds = array_column($matches, 'bucket_id');
                    $isIgnored = empty(array_diff($matchedBucketIds, $ignoredBucketIds));
                }

                $transaction = Transaction::create([
                    'tax_year_id' => $taxYear->id,
                    'import_id' => $import->id,
                    'date' => $row['date'],
                    'description' => $row['description'],
                    'amount' => $row['amount'],
                    'match_type' => $matchType,
                ]);

                foreach ($matches as $match) {
                    $transaction->buckets()->attach($match['bucket_id'], [
                        'assigned_via' => 'pattern',
                        'bucket_pattern_id' => $match['bucket_pattern_id'],
                    ]);
                }

                if ($matchType === 'unmatched') {
                    $unmatched++;
                } elseif ($isIgnored) {
                    $ignored++;
                } else {
                    $matched++;
                }
            }

            $import->update([
                'rows_total' => count($rows),
                'rows_matched' => $matched,
                'rows_unmatched' => $unmatched,
                'rows_ignored' => $ignored,
            ]);
        }
    }

    private function getBankTransactions(int $year, float $multiplier): array
    {
        $transactions = [];
        $m = $multiplier;

        // Client income — monthly payments
        for ($month = 1; $month <= 12; $month++) {
            $day = min(15, Carbon::create($year, $month)->daysInMonth);
            $transactions[] = ['date' => "{$year}-{$month}-{$day}", 'description' => 'ACH DEPOSIT ACME CORP PAYMENT', 'amount' => round(4500 * $m, 2)];

            if ($month % 2 === 0) {
                $transactions[] = ['date' => "{$year}-{$month}-20", 'description' => 'WIRE TRANSFER BRIGHTPATH DIGITAL LLC', 'amount' => round(3200 * $m, 2)];
            }

            if ($month >= 4 && $month <= 11) {
                $transactions[] = ['date' => "{$year}-{$month}-28", 'description' => 'ACH DEPOSIT CASCADE SOLUTIONS INC', 'amount' => round(2800 * $m, 2)];
            }
        }

        // Operating expenses — monthly recurring
        for ($month = 1; $month <= 12; $month++) {
            $transactions[] = ['date' => "{$year}-{$month}-01", 'description' => 'AWS SERVICES MONTHLY', 'amount' => round(-285.50 * $m, 2)];
            $transactions[] = ['date' => "{$year}-{$month}-01", 'description' => 'GITHUB INC TEAM PLAN', 'amount' => -44.00];
            $transactions[] = ['date' => "{$year}-{$month}-03", 'description' => 'SLACK TECHNOLOGIES BUSINESS+', 'amount' => round(-25.00 * $m, 2)];
            $transactions[] = ['date' => "{$year}-{$month}-05", 'description' => 'ADOBE CREATIVE CLOUD ALL APPS', 'amount' => -89.99];

            if ($month % 3 === 0) {
                $transactions[] = ['date' => "{$year}-{$month}-10", 'description' => 'FIGMA INC PROFESSIONAL', 'amount' => -45.00];
            }

            // Gusto — ignored (payroll module is source of truth)
            $transactions[] = ['date' => "{$year}-{$month}-" . min(28, Carbon::create($year, $month)->daysInMonth), 'description' => "GUSTO TYPE: NET 9138{$month}4001 CO ENTRY", 'amount' => round(-6800 * $m, 2)];
        }

        // Quarterly/annual expenses
        $transactions[] = ['date' => "{$year}-01-15", 'description' => 'HISCOX BUSINESS INSURANCE ANNUAL', 'amount' => -1250.00];
        $transactions[] = ['date' => "{$year}-01-10", 'description' => 'JETBRAINS PHPSTORM ALL PRODUCTS', 'amount' => -299.00];
        $transactions[] = ['date' => "{$year}-03-15", 'description' => 'TURBOTAX BUSINESS ONLINE', 'amount' => -189.99];
        $transactions[] = ['date' => "{$year}-02-01", 'description' => 'REGISTERED AGENT SERVICE ANNUAL', 'amount' => -125.00];
        $transactions[] = ['date' => "{$year}-06-22", 'description' => 'AMAZON MARKETPLACE - STANDING DESK CONVERTER', 'amount' => -349.99];
        $transactions[] = ['date' => "{$year}-09-15", 'description' => 'BEST BUY - WEBCAM AND MICROPHONE', 'amount' => -179.95];

        // Transfers — ignored
        $transactions[] = ['date' => "{$year}-03-01", 'description' => 'ONLINE TRANSFER TO SAVINGS', 'amount' => -5000.00];
        $transactions[] = ['date' => "{$year}-06-01", 'description' => 'ONLINE TRANSFER TO SAVINGS', 'amount' => -5000.00];
        $transactions[] = ['date' => "{$year}-09-01", 'description' => 'ONLINE TRANSFER TO SAVINGS', 'amount' => -5000.00];
        $transactions[] = ['date' => "{$year}-12-01", 'description' => 'ONLINE TRANSFER TO SAVINGS', 'amount' => -5000.00];

        // Cloudflare
        $transactions[] = ['date' => "{$year}-04-05", 'description' => 'CLOUDFLARE INC PRO PLAN', 'amount' => -240.00];
        $transactions[] = ['date' => "{$year}-10-05", 'description' => 'DIGITALOCEAN DROPLETS', 'amount' => -72.00];

        // A couple unmatched transactions
        $transactions[] = ['date' => "{$year}-05-18", 'description' => 'CHECK #1042 - STATE OF COLORADO', 'amount' => -50.00];
        $transactions[] = ['date' => "{$year}-11-12", 'description' => 'POS PURCHASE OFFICE DEPOT SUPPLIES', 'amount' => -87.43];

        return $transactions;
    }

    private function seedPayroll(TaxYear $ty2023, TaxYear $ty2024, TaxYear $ty2025): void
    {
        foreach ([$ty2023, $ty2024, $ty2025] as $taxYear) {
            $year = $taxYear->year;
            $multiplier = match ($year) { 2023 => 0.85, 2024 => 1.0, 2025 => 1.05 };

            // Employee payroll
            $empImport = PayrollImport::create([
                'tax_year_id' => $taxYear->id,
                'type' => 'employee',
                'original_filename' => "gusto-employee-payroll-{$year}.csv",
                'imported_at' => now(),
            ]);

            $empCount = 0;

            for ($month = 1; $month <= 12; $month++) {
                $lastDay = Carbon::create($year, $month)->daysInMonth;
                $payDate = "{$year}-{$month}-" . min(28, $lastDay);
                $period = sprintf('%02d/01/%d - %02d/%02d/%d, Regular', $month, $year, $month, $lastDay, $year);

                // Alex Chen — Officer
                $grossAlex = round(5000 * $multiplier, 2);
                $eeTaxAlex = round($grossAlex * 0.0765 + $grossAlex * 0.05, 2);
                $erTaxAlex = round($grossAlex * 0.0765 + 42.00, 2);
                $netAlex = round($grossAlex - $eeTaxAlex, 2);

                PayrollEntry::create([
                    'tax_year_id' => $taxYear->id, 'payroll_import_id' => $empImport->id,
                    'type' => 'employee', 'name' => 'Alex Chen', 'is_officer' => true,
                    'date' => $payDate, 'gross_pay' => $grossAlex,
                    'employee_deductions' => 0, 'employer_contributions' => 0,
                    'employee_taxes' => $eeTaxAlex, 'employer_taxes' => $erTaxAlex,
                    'net_pay' => $netAlex, 'employer_cost' => round($grossAlex + $erTaxAlex, 2),
                    'check_amount' => $netAlex, 'notes' => $period,
                ]);
                $empCount++;

                // Jamie Rivera — Employee
                $grossJamie = round(3500 * $multiplier, 2);
                $eeTaxJamie = round($grossJamie * 0.0765 + $grossJamie * 0.04, 2);
                $erTaxJamie = round($grossJamie * 0.0765 + 42.00, 2);
                $netJamie = round($grossJamie - $eeTaxJamie, 2);

                PayrollEntry::create([
                    'tax_year_id' => $taxYear->id, 'payroll_import_id' => $empImport->id,
                    'type' => 'employee', 'name' => 'Jamie Rivera', 'is_officer' => false,
                    'date' => $payDate, 'gross_pay' => $grossJamie,
                    'employee_deductions' => 0, 'employer_contributions' => 0,
                    'employee_taxes' => $eeTaxJamie, 'employer_taxes' => $erTaxJamie,
                    'net_pay' => $netJamie, 'employer_cost' => round($grossJamie + $erTaxJamie, 2),
                    'check_amount' => $netJamie, 'notes' => $period,
                ]);
                $empCount++;
            }

            // Tax reconciliation entry (Q1)
            PayrollEntry::create([
                'tax_year_id' => $taxYear->id, 'payroll_import_id' => $empImport->id,
                'type' => 'employee', 'name' => 'Alex Chen', 'is_officer' => true,
                'date' => "{$year}-03-31", 'gross_pay' => 0,
                'employee_deductions' => 0, 'employer_contributions' => 0,
                'employee_taxes' => 0, 'employer_taxes' => -18.45,
                'net_pay' => 0, 'employer_cost' => -18.45,
                'check_amount' => 0, 'notes' => "03/31/{$year}, Tax reconciliation",
            ]);
            $empCount++;

            $empImport->update(['rows_total' => $empCount]);

            // US Contractors
            $usImport = PayrollImport::create([
                'tax_year_id' => $taxYear->id,
                'type' => 'us_contractor',
                'original_filename' => "gusto-us-contractors-{$year}.csv",
                'imported_at' => now(),
            ]);

            PayrollEntry::create([
                'tax_year_id' => $taxYear->id, 'payroll_import_id' => $usImport->id,
                'type' => 'us_contractor', 'name' => 'Morgan Blake',
                'date' => "{$year}-12-31", 'gross_pay' => round(18000 * $multiplier, 2),
                'department' => 'Design',
            ]);
            PayrollEntry::create([
                'tax_year_id' => $taxYear->id, 'payroll_import_id' => $usImport->id,
                'type' => 'us_contractor', 'name' => 'Taylor Osman',
                'date' => "{$year}-12-31", 'gross_pay' => round(9600 * $multiplier, 2),
                'department' => 'Marketing',
            ]);
            $usImport->update(['rows_total' => 2]);

            // International Contractors
            $intlImport = PayrollImport::create([
                'tax_year_id' => $taxYear->id,
                'type' => 'intl_contractor',
                'original_filename' => "gusto-intl-contractors-{$year}.csv",
                'imported_at' => now(),
            ]);

            $intlCount = 0;
            for ($month = 1; $month <= 12; $month++) {
                PayrollEntry::create([
                    'tax_year_id' => $taxYear->id, 'payroll_import_id' => $intlImport->id,
                    'type' => 'intl_contractor', 'name' => 'Lena Kowalski',
                    'date' => "{$year}-{$month}-15", 'gross_pay' => round(2400 * $multiplier, 2),
                    'wage_type' => 'Fixed price', 'currency' => 'EUR',
                    'foreign_amount' => round(2200 * $multiplier, 2),
                    'payment_status' => 'Paid',
                ]);
                $intlCount++;
            }
            $intlImport->update(['rows_total' => $intlCount]);
        }
    }

    private function seedCrypto(): void
    {
        $btc = CryptoAsset::create(['name' => 'Bitcoin', 'symbol' => 'BTC']);
        $eth = CryptoAsset::create(['name' => 'Ethereum', 'symbol' => 'ETH']);

        // ─── BTC Buys ───
        $btcBuy1 = CryptoBuy::create([
            'crypto_asset_id' => $btc->id, 'date' => '2022-06-15',
            'quantity' => 0.5, 'cost_per_unit' => 21500.00,
            'total_cost' => 10755.00, 'fee' => 5.00, 'quantity_remaining' => 0.25,
        ]);
        $btcBuy2 = CryptoBuy::create([
            'crypto_asset_id' => $btc->id, 'date' => '2023-01-10',
            'quantity' => 0.3, 'cost_per_unit' => 17200.00,
            'total_cost' => 5163.50, 'fee' => 3.50, 'quantity_remaining' => 0.3,
        ]);
        $btcBuy3 = CryptoBuy::create([
            'crypto_asset_id' => $btc->id, 'date' => '2023-09-20',
            'quantity' => 0.2, 'cost_per_unit' => 26800.00,
            'total_cost' => 5364.00, 'fee' => 4.00, 'quantity_remaining' => 0.2,
        ]);
        $btcBuy4 = CryptoBuy::create([
            'crypto_asset_id' => $btc->id, 'date' => '2024-03-05',
            'quantity' => 0.15, 'cost_per_unit' => 62000.00,
            'total_cost' => 9305.00, 'fee' => 5.00, 'quantity_remaining' => 0.15,
        ]);

        // ─── BTC Sells (allocated) ───
        $btcSell1 = CryptoSell::create([
            'crypto_asset_id' => $btc->id, 'date' => '2024-11-15',
            'quantity' => 0.25, 'price_per_unit' => 88000.00,
            'total_proceeds' => 21995.00, 'fee' => 5.00,
            'total_cost_basis' => 5375.00, 'gain_loss' => 16620.00,
        ]);
        // Allocated from btcBuy1 (0.25 of 0.5)
        $btcSell1->buys()->attach($btcBuy1->id, [
            'quantity' => 0.25, 'cost_basis' => 5375.00, 'is_long_term' => true,
        ]);

        // ─── BTC Unallocated sell ───
        CryptoSell::create([
            'crypto_asset_id' => $btc->id, 'date' => '2025-02-10',
            'quantity' => 0.1, 'price_per_unit' => 97500.00,
            'total_proceeds' => 9746.00, 'fee' => 4.00,
        ]);

        // ─── ETH Buys ───
        $ethBuy1 = CryptoBuy::create([
            'crypto_asset_id' => $eth->id, 'date' => '2022-09-01',
            'quantity' => 5.0, 'cost_per_unit' => 1550.00,
            'total_cost' => 7755.00, 'fee' => 5.00, 'quantity_remaining' => 2.0,
        ]);
        $ethBuy2 = CryptoBuy::create([
            'crypto_asset_id' => $eth->id, 'date' => '2023-04-12',
            'quantity' => 3.0, 'cost_per_unit' => 1890.00,
            'total_cost' => 5674.00, 'fee' => 4.00, 'quantity_remaining' => 3.0,
        ]);
        $ethBuy3 = CryptoBuy::create([
            'crypto_asset_id' => $eth->id, 'date' => '2024-06-20',
            'quantity' => 2.0, 'cost_per_unit' => 3450.00,
            'total_cost' => 6905.00, 'fee' => 5.00, 'quantity_remaining' => 2.0,
        ]);

        // ─── ETH Sells (allocated) ───
        $ethSell1 = CryptoSell::create([
            'crypto_asset_id' => $eth->id, 'date' => '2024-03-15',
            'quantity' => 3.0, 'price_per_unit' => 3600.00,
            'total_proceeds' => 10795.00, 'fee' => 5.00,
            'total_cost_basis' => 4650.00, 'gain_loss' => 6145.00,
        ]);
        // Allocated from ethBuy1 (3.0 of 5.0)
        $ethSell1->buys()->attach($ethBuy1->id, [
            'quantity' => 3.0, 'cost_basis' => 4650.00, 'is_long_term' => true,
        ]);
    }

    private function seedBalanceSheets(TaxYear $ty2023, TaxYear $ty2024, TaxYear $ty2025): void
    {
        $btc = CryptoAsset::where('symbol', 'BTC')->first();
        $eth = CryptoAsset::where('symbol', 'ETH')->first();

        // 2023
        BalanceSheetItem::create([
            'tax_year_id' => $ty2023->id, 'crypto_asset_id' => $btc->id,
            'label' => 'Bitcoin', 'asset_type' => 'crypto',
            'quantity' => 1.0, 'unit_price_year_end' => 42258.00,
            'total_value' => 42258.00, 'sort_order' => 1,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2023->id, 'crypto_asset_id' => $eth->id,
            'label' => 'Ethereum', 'asset_type' => 'crypto',
            'quantity' => 8.0, 'unit_price_year_end' => 2352.00,
            'total_value' => 18816.00, 'sort_order' => 2,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2023->id, 'label' => 'NVIDIA', 'asset_type' => 'stock',
            'ticker_symbol' => 'NVDA', 'quantity' => 50,
            'unit_price_year_end' => 495.22, 'total_value' => 24761.00, 'sort_order' => 3,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2023->id, 'label' => 'Business Checking', 'asset_type' => 'cash',
            'total_value' => 32500.00, 'sort_order' => 4,
        ]);

        // 2024
        BalanceSheetItem::create([
            'tax_year_id' => $ty2024->id, 'crypto_asset_id' => $btc->id,
            'label' => 'Bitcoin', 'asset_type' => 'crypto',
            'quantity' => 0.9, 'unit_price_year_end' => 93425.00,
            'total_value' => 84082.50, 'sort_order' => 1,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2024->id, 'crypto_asset_id' => $eth->id,
            'label' => 'Ethereum', 'asset_type' => 'crypto',
            'quantity' => 7.0, 'unit_price_year_end' => 3350.00,
            'total_value' => 23450.00, 'sort_order' => 2,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2024->id, 'label' => 'NVIDIA', 'asset_type' => 'stock',
            'ticker_symbol' => 'NVDA', 'quantity' => 50,
            'unit_price_year_end' => 134.29, 'total_value' => 6714.50, 'sort_order' => 3,
            'notes' => 'Post-split price (10:1 split June 2024)',
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2024->id, 'label' => 'Business Checking', 'asset_type' => 'cash',
            'total_value' => 41200.00, 'sort_order' => 4,
        ]);

        // 2025
        BalanceSheetItem::create([
            'tax_year_id' => $ty2025->id, 'crypto_asset_id' => $btc->id,
            'label' => 'Bitcoin', 'asset_type' => 'crypto',
            'quantity' => 0.8, 'unit_price_year_end' => 105200.00,
            'total_value' => 84160.00, 'sort_order' => 1,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2025->id, 'crypto_asset_id' => $eth->id,
            'label' => 'Ethereum', 'asset_type' => 'crypto',
            'quantity' => 7.0, 'unit_price_year_end' => 3800.00,
            'total_value' => 26600.00, 'sort_order' => 2,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2025->id, 'label' => 'NVIDIA', 'asset_type' => 'stock',
            'ticker_symbol' => 'NVDA', 'quantity' => 50,
            'unit_price_year_end' => 148.50, 'total_value' => 7425.00, 'sort_order' => 3,
        ]);
        BalanceSheetItem::create([
            'tax_year_id' => $ty2025->id, 'label' => 'Business Checking', 'asset_type' => 'cash',
            'total_value' => 48750.00, 'sort_order' => 4,
        ]);
    }
}