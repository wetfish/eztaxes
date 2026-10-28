<?php

namespace Database\Seeders;

use App\Models\TaxYear;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultBucketGroupsSeeder::class,
            DefaultCsvTemplatesSeeder::class,
        ]);

        // Demo data is added once, to a database without tax years, so
        // seeding again on every deploy never duplicates it
        if (config('demo.enabled') && TaxYear::doesntExist()) {
            $this->call(DemoSeeder::class);
        }
    }
}
