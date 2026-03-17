<?php

namespace App\Console\Commands;

use App\Models\Bucket;
use App\Models\BucketPattern;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportLegacyBuckets extends Command
{
    protected $signature = 'buckets:import-legacy
                            {file? : Filename within the legacy/ directory (default: buckets.txt)}
                            {--fresh : Delete all existing buckets and patterns before importing}';

    protected $description = 'Import buckets and regex patterns from a serialized PHP array file in the legacy/ directory';

    private const LEGACY_DIR = '/var/www/legacy';

    public function handle(): int
    {
        $filename = $this->argument('file') ?? 'buckets.txt';
        $filepath = self::LEGACY_DIR . '/' . $filename;

        if (!file_exists($filepath)) {
            $this->error("File not found: legacy/{$filename}");
            $this->newLine();
            $this->info('Place your serialized bucket data in the legacy/ directory at the project root.');
            $this->info('Generate it from your legacy script with:');
            $this->line('  file_put_contents(\'legacy/buckets.txt\', serialize($groupedKeywords));');
            $this->newLine();

            $filename = $this->ask('Enter a filename in the legacy/ directory (or press Ctrl+C to cancel)');

            if (empty($filename)) {
                return Command::FAILURE;
            }

            $filepath = self::LEGACY_DIR . '/' . $filename;

            if (!file_exists($filepath)) {
                $this->error("File not found: legacy/{$filename}");
                return Command::FAILURE;
            }
        }

        $contents = file_get_contents($filepath);

        // Deserialize with allowed_classes disabled to prevent object injection
        $keywords = unserialize(trim($contents), ['allowed_classes' => false]);

        if (!is_array($keywords) || empty($keywords)) {
            $this->error('File must contain a serialized array with bucket names as keys and arrays of patterns as values.');
            return Command::FAILURE;
        }

        $this->info("Loaded " . count($keywords) . " bucket definitions from legacy/{$filename}");

        if ($this->option('fresh')) {
            if ($this->confirm('This will delete ALL existing buckets and patterns. Are you sure?')) {
                BucketPattern::truncate();
                Bucket::truncate();
                $this->info('Cleared all existing buckets and patterns.');
            } else {
                $this->info('Aborted.');
                return Command::SUCCESS;
            }
        }

        $bucketsCreated = 0;
        $patternsCreated = 0;
        $bucketsSkipped = 0;

        foreach ($keywords as $groupName => $patterns) {
            $slug = Str::slug($groupName);

            if (Bucket::where('slug', $slug)->exists()) {
                $this->warn("Bucket '{$groupName}' (slug: {$slug}) already exists, skipping.");
                $bucketsSkipped++;
                continue;
            }

            $behavior = ($groupName === 'ignored') ? 'ignored' : 'normal';

            $bucket = Bucket::create([
                'name' => $groupName,
                'slug' => $slug,
                'behavior' => $behavior,
                'sort_order' => $bucketsCreated,
                'is_active' => true,
            ]);

            $bucketsCreated++;

            if (!is_array($patterns)) {
                $this->warn("  Skipping patterns for '{$groupName}' — expected an array.");
                continue;
            }

            foreach ($patterns as $priority => $pattern) {
                if (empty(trim($pattern))) {
                    continue;
                }

                BucketPattern::create([
                    'bucket_id' => $bucket->id,
                    'pattern' => $pattern,
                    'priority' => $priority,
                    'is_active' => true,
                ]);

                $patternsCreated++;
            }

            $this->line("  Created bucket '{$groupName}' with " . count($patterns) . " patterns");
        }

        $this->newLine();
        $this->info("Import complete:");
        $this->info("  Buckets created: {$bucketsCreated}");
        $this->info("  Buckets skipped (already exist): {$bucketsSkipped}");
        $this->info("  Patterns created: {$patternsCreated}");

        return Command::SUCCESS;
    }
}