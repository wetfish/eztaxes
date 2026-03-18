<?php

namespace Database\Seeders;

use App\Models\BucketGroup;
use Illuminate\Database\Seeder;

class DefaultBucketGroupsSeeder extends Seeder
{
    /**
     * Seed default bucket groups for S-Corp organization.
     * Idempotent — only creates groups that don't already exist.
     */
    public function run(): void
    {
        $groups = [
            ['name' => 'Client Income', 'slug' => 'client-income', 'sort_order' => 1],
            ['name' => 'Operating Expenses', 'slug' => 'operating-expenses', 'sort_order' => 2],
            ['name' => 'Payroll', 'slug' => 'payroll', 'sort_order' => 3],
            ['name' => 'Assets', 'slug' => 'assets', 'sort_order' => 4],
            ['name' => 'Ignored', 'slug' => 'ignored', 'sort_order' => 5],
        ];

        foreach ($groups as $group) {
            BucketGroup::firstOrCreate(
                ['slug' => $group['slug']],
                [
                    'name' => $group['name'],
                    'sort_order' => $group['sort_order'],
                ]
            );
        }
    }
}