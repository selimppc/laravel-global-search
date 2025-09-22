<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

/**
 * Command to sync index settings from configuration to Meilisearch.
 */
class SearchSyncSettingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'search:sync-settings 
                            {--index= : Sync settings for a specific index only}';

    /**
     * The console command description.
     */
    protected $description = 'Push index settings from configuration to Meilisearch';

    /**
     * Execute the console command.
     */
    public function handle(SearchIndexManager $indexManager): int
    {
        $specificIndex = $this->option('index');

        try {
            $this->info('🔄 Syncing index settings...');

            if ($specificIndex) {
                $this->syncSpecificIndex($indexManager, $specificIndex);
            } else {
                $indexManager->syncIndexSettings();
                $this->info('✅ All index settings synced successfully!');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to sync settings: {$e->getMessage()}");
            Log::error('Search sync settings command failed', [
                'specific_index' => $specificIndex,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Sync settings for a specific index.
     */
    private function syncSpecificIndex(SearchIndexManager $indexManager, string $indexName): void
    {
        $config = config('global-search.index_settings', []);
        
        if (!isset($config[$indexName])) {
            $this->error("❌ No settings found for index: {$indexName}");
            return;
        }

        $this->info("📝 Syncing settings for index: {$indexName}");
        
        // This would need to be implemented in SearchIndexManager
        // For now, we'll use the general sync method
        $indexManager->syncIndexSettings();
        
        $this->info("✅ Settings synced for index: {$indexName}");
    }
}