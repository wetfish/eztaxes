<?php

namespace Database\Seeders;

use App\Models\CsvTemplate;
use Illuminate\Database\Seeder;

class DefaultCsvTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ─── Gusto ───
            [
                'name' => 'Gusto Employee Payroll',
                'detection_headers' => ['Employee', 'Gross earnings', 'Net pay', 'Payroll pay date'],
                'column_mapping' => [
                    'date' => null,
                    'amount' => null,
                    'description' => null,
                    'date_header' => 'Payroll pay date',
                    'amount_header' => 'Check amount',
                    'description_header' => 'Employee',
                ],
            ],
            [
                'name' => 'Gusto US Contractors',
                'detection_headers' => ['Last Name', 'First Name', 'Total Amount'],
                'column_mapping' => [
                    'date' => -1,
                    'amount' => null,
                    'description' => null,
                    'amount_header' => 'Total Amount',
                    'description_header' => 'Last Name',
                ],
            ],
            [
                'name' => 'Gusto International Contractors',
                'detection_headers' => ['Contractor name', 'Processing date', 'USD amount', 'Wage type'],
                'column_mapping' => [
                    'date' => null,
                    'amount' => null,
                    'description' => null,
                    'date_header' => 'Processing date',
                    'amount_header' => 'USD amount',
                    'description_header' => 'Contractor name',
                ],
            ],

            // ─── Crypto ───
            [
                'name' => 'CashApp Crypto',
                'detection_headers' => ['Transaction Type', 'Asset Amount', 'Asset Price'],
                'column_mapping' => [
                    'format' => 'cashapp',
                ],
            ],
            [
                'name' => 'Coinbase Gain/Loss',
                'detection_headers' => ['Date of Disposition', 'Cost basis (USD)', 'Proceeds (USD)'],
                'column_mapping' => [
                    'format' => 'coinbase',
                ],
            ],
        ];

        foreach ($templates as $data) {
            CsvTemplate::firstOrCreate(
                ['name' => $data['name']],
                [
                    'column_mapping' => $data['column_mapping'],
                    'detection_headers' => $data['detection_headers'],
                    'is_seeded' => true,
                ]
            );
        }
    }
}