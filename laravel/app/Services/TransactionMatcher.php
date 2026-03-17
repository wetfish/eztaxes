<?php

namespace App\Services;

use App\Models\BucketPattern;
use Illuminate\Support\Collection;

class TransactionMatcher
{
    private Collection $patterns;

    public function __construct()
    {
        $this->loadPatterns();
    }

    /**
     * Load all active patterns, ordered by priority, eager-loading their bucket.
     */
    public function loadPatterns(): void
    {
        $this->patterns = BucketPattern::where('is_active', true)
            ->with('bucket')
            ->orderBy('priority')
            ->get();
    }

    /**
     * Match a transaction description against all active patterns.
     * Returns an array of matches, each containing the bucket and the pattern that matched.
     * A transaction can match multiple buckets.
     *
     * @return array<int, array{bucket_id: int, bucket_pattern_id: int, bucket_slug: string}>
     */
    public function match(string $description): array
    {
        $matches = [];
        $matchedBucketIds = [];

        foreach ($this->patterns as $pattern) {
            // Skip if we've already matched this bucket for this transaction
            if (in_array($pattern->bucket_id, $matchedBucketIds)) {
                continue;
            }

            // Test the regex pattern against the description
            if ($this->testPattern($pattern->pattern, $description)) {
                $matches[] = [
                    'bucket_id' => $pattern->bucket_id,
                    'bucket_pattern_id' => $pattern->id,
                    'bucket_slug' => $pattern->bucket->slug,
                ];

                $matchedBucketIds[] = $pattern->bucket_id;
            }
        }

        return $matches;
    }

    /**
     * Test a single regex pattern against a description.
     * Wraps the pattern in delimiters and uses case-insensitive matching,
     * mirroring the legacy script's behavior.
     */
    private function testPattern(string $pattern, string $description): bool
    {
        // Suppress warnings from invalid regex patterns
        $result = @preg_match("/{$pattern}/i", $description);

        if ($result === false) {
            // Invalid regex pattern — log or silently skip
            return false;
        }

        return $result === 1;
    }
}