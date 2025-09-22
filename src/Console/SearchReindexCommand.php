<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

/**
 * Command to reindex search indexes.
 */
class SearchReindexCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'search:reindex 
                            {index? : The specific index to reindex}
                            {--ids= : Comma-separated list of specific IDs to reindex}
                            {--chunk=1000 : Number of records to process in each chunk}
                            {--force : Force reindexing even if index is not empty}';

    /**
     * The console command description.
     */
    protected $description = 'Reindex one or all search indexes';

    /**
     * Execute the console command.
     */
    public function handle(SearchIndexManager $indexManager): int
    {
        $indexName = $this->argument('index');
        $specificIds = $this->getSpecificIds();
        $chunkSize = (int) $this->option('chunk');
        $force = $this->option('force');

        $this->info('Starting search index reindexing...');

        try {
            $mappings = $this->getMappingsToReindex($indexManager, $indexName);
            
            if (empty($mappings)) {
                $this->warn('No mappings found to reindex.');
                return self::SUCCESS;
            }

            foreach ($mappings as $mapping) {
                $this->reindexMapping($indexManager, $mapping, $specificIds, $chunkSize, $force);
            }

            $this->info('✅ Reindexing completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Reindexing failed: {$e->getMessage()}");
            Log::error('Search reindex command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Get specific IDs from the command option.
     */
    private function getSpecificIds(): ?array
    {
        $idsOption = $this->option('ids');
        
        if (!$idsOption) {
            return null;
        }

        return array_filter(array_map('trim', explode(',', $idsOption)));
    }

    /**
     * Get mappings to reindex based on the index argument.
     */
    private function getMappingsToReindex(SearchIndexManager $indexManager, ?string $indexName): array
    {
        $allMappings = $indexManager->getMappings();
        
        if (!$indexName) {
            return $allMappings;
        }

        return array_filter($allMappings, fn($mapping) => $mapping['index'] === $indexName);
    }

    /**
     * Reindex a specific mapping.
     */
    private function reindexMapping(
        SearchIndexManager $indexManager, 
        array $mapping, 
        ?array $specificIds, 
        int $chunkSize, 
        bool $force
    ): void {
        $modelClass = $mapping['model'];
        $indexName = $mapping['index'];
        
        $this->info("📝 Processing index: {$indexName}");

        try {
            $model = new $modelClass;
            $primaryKey = $mapping['primary_key'] ?? $model->getKeyName();

            $query = $modelClass::query();
            
            if ($specificIds) {
                $query->whereIn($primaryKey, $specificIds);
            }

            $totalCount = $query->count();
            
            if ($totalCount === 0) {
                $this->warn("   No records found for {$modelClass}");
                return;
            }

            $this->info("   Found {$totalCount} records to reindex");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            $query->orderBy($primaryKey)->chunk($chunkSize, function ($chunk) use ($indexManager, $modelClass, $progressBar) {
                $modelIds = $chunk->pluck($chunk->first()->getKeyName())->all();
                
                try {
                    $indexManager->indexModels($modelClass, $modelIds);
                    $progressBar->advance($chunk->count());
                } catch (\Exception $e) {
                    $this->error("   Failed to index chunk: {$e->getMessage()}");
                    throw $e;
                }
            });

            $progressBar->finish();
            $this->newLine();
            $this->info("   ✅ Successfully reindexed {$indexName}");

        } catch (\Exception $e) {
            $this->error("   ❌ Failed to reindex {$indexName}: {$e->getMessage()}");
            throw $e;
        }
    }
}