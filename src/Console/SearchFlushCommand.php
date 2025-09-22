<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

/**
 * Command to flush (clear) a search index.
 */
class SearchFlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'search:flush 
                            {index : The name of the index to flush}
                            {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Delete all documents from a search index';

    /**
     * Execute the console command.
     */
    public function handle(SearchIndexManager $indexManager): int
    {
        $indexName = $this->argument('index');
        $confirmed = $this->option('confirm');

        if (!$confirmed) {
            if (!$this->confirm("Are you sure you want to flush the '{$indexName}' index? This will delete ALL documents.")) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        try {
            $this->info("🗑️  Flushing index: {$indexName}");
            
            $indexManager->flushIndex($indexName);
            
            $this->info("✅ Successfully flushed index: {$indexName}");
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to flush index '{$indexName}': {$e->getMessage()}");
            Log::error('Search flush command failed', [
                'index' => $indexName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}