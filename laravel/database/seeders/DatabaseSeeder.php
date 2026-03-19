<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultBucketGroupsSeeder::class,
            DefaultCsvTemplatesSeeder::class,
        ]);

        if (config('demo.enabled')) {
            $this->call(DemoSeeder::class);
        }
    }
}