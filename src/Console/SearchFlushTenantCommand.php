<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

/**
 * Command to flush all indexes for a specific tenant.
 */
class SearchFlushTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'global-search:flush-tenant 
                            {tenant : The tenant ID to flush}
                            {--index= : Specific index to flush (optional)}
                            {--force : Force flush without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Flush all search indexes for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(SearchIndexManager $indexManager, TenantResolver $tenantResolver): int
    {
        $tenantId = $this->argument('tenant');
        $indexName = $this->option('index');
        $force = $this->option('force');

        // Validate tenant exists
        if ($tenantResolver->isMultiTenant()) {
            $allTenants = $tenantResolver->getAllTenants();
            if (!in_array($tenantId, $allTenants)) {
                $this->error("Tenant '{$tenantId}' not found. Available tenants: " . implode(', ', $allTenants));
                return 1;
            }
        }

        // Confirm operation
        if (!$force) {
            $message = $indexName 
                ? "Flush index '{$indexName}' for tenant '{$tenantId}'?"
                : "Flush all indexes for tenant '{$tenantId}'?";
                
            if (!$this->confirm($message)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $this->info("Starting flush for tenant: {$tenantId}");
            
            if ($indexName) {
                $this->flushIndexForTenant($indexManager, $indexName, $tenantId);
            } else {
                $this->flushAllIndexesForTenant($indexManager, $tenantId);
            }
            
            $this->info("Flush completed for tenant: {$tenantId}");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Flush failed for tenant '{$tenantId}': " . $e->getMessage());
            Log::error('Tenant flush failed', [
                'tenant' => $tenantId,
                'index' => $indexName,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Flush a specific index for a tenant.
     */
    private function flushIndexForTenant(SearchIndexManager $indexManager, string $indexName, string $tenantId): void
    {
        $this->info("Flushing index: {$indexName}");
        $indexManager->flushIndex($indexName, $tenantId);
        $this->info("✓ Index '{$indexName}' flushed for tenant '{$tenantId}'");
    }

    /**
     * Flush all indexes for a tenant.
     */
    private function flushAllIndexesForTenant(SearchIndexManager $indexManager, string $tenantId): void
    {
        $mappings = $indexManager->getMappings();
        
        if (empty($mappings)) {
            $this->warn('No model mappings configured');
            return;
        }

        $indexes = array_unique(array_column($mappings, 'index'));
        
        foreach ($indexes as $indexName) {
            $this->flushIndexForTenant($indexManager, $indexName, $tenantId);
        }
    }
}
