<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Services\GlobalSearchService;

/**
 * Command to warm the search cache with common queries.
 */
class SearchWarmCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'search:warm-cache 
                            {--queries=* : Specific queries to warm the cache with}
                            {--limit=10 : Number of results to cache per query}
                            {--file= : Path to file containing queries (one per line)}';

    /**
     * The console command description.
     */
    protected $description = 'Warm the search cache with common queries';

    /**
     * Execute the console command.
     */
    public function handle(GlobalSearchService $searchService): int
    {
        $queries = $this->getQueriesToWarm();
        $limit = (int) $this->option('limit');

        if (empty($queries)) {
            $this->warn('No queries provided to warm the cache.');
            return self::SUCCESS;
        }

        $this->info("🔥 Warming cache with " . count($queries) . " queries...");

        $successCount = 0;
        $failureCount = 0;

        foreach ($queries as $query) {
            try {
                $this->info("   Warming: '{$query}'");
                $searchService->search($query, [], $limit);
                $successCount++;
            } catch (\Exception $e) {
                $this->error("   ❌ Failed to warm query '{$query}': {$e->getMessage()}");
                $failureCount++;
                Log::error('Cache warming failed for query', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("✅ Cache warming completed!");
        $this->info("   Success: {$successCount}");
        
        if ($failureCount > 0) {
            $this->warn("   Failures: {$failureCount}");
        }

        return $failureCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Get queries to warm the cache with.
     */
    private function getQueriesToWarm(): array
    {
        $queries = (array) $this->option('queries');
        $filePath = $this->option('file');

        if ($filePath && file_exists($filePath)) {
            $fileQueries = array_filter(array_map('trim', file($filePath, FILE_IGNORE_NEW_LINES)));
            $queries = array_merge($queries, $fileQueries);
        }

        return array_unique(array_filter($queries));
    }
}