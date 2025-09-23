<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

/**
 * Command to reindex all models for a specific tenant.
 */
class SearchReindexTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'global-search:reindex-tenant 
                            {tenant : The tenant ID to reindex}
                            {--model= : Specific model class to reindex (optional)}
                            {--force : Force reindexing without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Reindex all searchable models for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(SearchIndexManager $indexManager, TenantResolver $tenantResolver): int
    {
        $tenantId = $this->argument('tenant');
        $modelClass = $this->option('model');
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
            $message = $modelClass 
                ? "Reindex model '{$modelClass}' for tenant '{$tenantId}'?"
                : "Reindex all models for tenant '{$tenantId}'?";
                
            if (!$this->confirm($message)) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        try {
            $this->info("Starting reindex for tenant: {$tenantId}");
            
            if ($modelClass) {
                $this->reindexModelForTenant($indexManager, $modelClass, $tenantId);
            } else {
                $this->reindexAllModelsForTenant($indexManager, $tenantId);
            }
            
            $this->info("Reindex completed for tenant: {$tenantId}");
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Reindex failed for tenant '{$tenantId}': " . $e->getMessage());
            Log::error('Tenant reindex failed', [
                'tenant' => $tenantId,
                'model' => $modelClass,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Reindex a specific model for a tenant.
     */
    private function reindexModelForTenant(SearchIndexManager $indexManager, string $modelClass, string $tenantId): void
    {
        $mapping = $indexManager->findMappingByModel($modelClass);
        if (!$mapping) {
            throw new \InvalidArgumentException("No mapping found for model: {$modelClass}");
        }

        $this->info("Reindexing model: {$modelClass}");
        
        // Get all model IDs for this tenant
        $model = new $modelClass;
        $query = $modelClass::query();
        
        // Add tenant filter if model has tenant_id
        if ($model->hasAttribute('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        
        $modelIds = $query->pluck($model->getKeyName())->toArray();
        
        if (empty($modelIds)) {
            $this->warn("No models found for tenant '{$tenantId}' in model '{$modelClass}'");
            return;
        }

        $this->info("Found " . count($modelIds) . " models to reindex");
        
        // Process in batches
        $batchSize = Config::get('global-search.pipeline.batch_size', 1000);
        $batches = array_chunk($modelIds, $batchSize);
        
        $progressBar = $this->output->createProgressBar(count($modelIds));
        $progressBar->start();
        
        foreach ($batches as $batch) {
            $indexManager->indexModels($modelClass, $batch, $tenantId);
            $progressBar->advance(count($batch));
        }
        
        $progressBar->finish();
        $this->newLine();
    }

    /**
     * Reindex all models for a tenant.
     */
    private function reindexAllModelsForTenant(SearchIndexManager $indexManager, string $tenantId): void
    {
        $mappings = $indexManager->getMappings();
        
        if (empty($mappings)) {
            $this->warn('No model mappings configured');
            return;
        }

        foreach ($mappings as $mapping) {
            $modelClass = $mapping['model'] ?? null;
            if (!$modelClass || !class_exists($modelClass)) {
                continue;
            }

            $this->reindexModelForTenant($indexManager, $modelClass, $tenantId);
        }
    }
}
