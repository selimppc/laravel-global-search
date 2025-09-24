<?php

namespace LaravelGlobalSearch\GlobalSearch\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use LaravelGlobalSearch\GlobalSearch\Support\DataTransformerManager;
use Meilisearch\Client;

/**
 * Modern, efficient indexing job for scale.
 */
class IndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $modelClass,
        private array $modelIds,
        private ?string $tenant = null
    ) {
        $this->onQueue('search');
    }

    public function handle(TenantResolver $tenantResolver, DataTransformerManager $transformerManager): void
    {
        try {
            $tenant = $this->tenant ?? $tenantResolver->getCurrentTenant();
            
            // Initialize tenant context to access tenant-specific models
            if ($tenant) {
                $this->initializeTenantContext($tenant);
            }
            
            $client = App::make(Client::class);
            
            // Process models in chunks for better memory usage
            $documents = [];
            $chunkSize = 100; // Process 100 models at a time
            
            foreach (array_chunk($this->modelIds, $chunkSize) as $chunk) {
                $models = $this->modelClass::whereIn('id', $chunk)->get();
                
                if ($models->isEmpty()) {
                    Log::warning('No models found for chunk', [
                        'model' => $this->modelClass,
                        'chunk' => $chunk,
                        'tenant' => $tenant
                    ]);
                    continue;
                }

                // Transform models to documents using transformer manager
                $chunkDocuments = $models->map(fn($model) => $transformerManager->transform($model, $tenant))->toArray();
                $documents = array_merge($documents, $chunkDocuments);
                
                // Free memory
                unset($models, $chunkDocuments);
            }
            
            if (empty($documents)) {
                Log::error('No models found for indexing', [
                    'model' => $this->modelClass,
                    'ids' => $this->modelIds,
                    'tenant' => $tenant
                ]);
                return;
            }
            
            // Get index name
            $indexName = $this->getIndexName($tenant);
            
            // Get index and ensure it exists with proper primary key
            $index = $client->index($indexName);
            
            Log::info("About to ensure primary key for index: {$indexName}");
            $this->ensureIndexExistsWithPrimaryKey($client, $indexName);
            Log::info("Finished ensuring primary key for index: {$indexName}");
            
            // Index documents
            $index->addDocuments($documents);
            
        } catch (\Exception $e) {
            Log::error('Indexing job failed', [
                'model' => $this->modelClass,
                'tenant' => $this->tenant,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }


    private function generateUrl($model): string
    {
        $routeName = strtolower(class_basename($model)) . '.show';
        
        if (app('router')->has($routeName)) {
            return route($routeName, $model->id);
        }
        
        return url('/') . '/' . strtolower(class_basename($model)) . '/' . $model->id;
    }

    private function getIndexName(?string $tenant): string
    {
        // Get the correct index name from configuration
        $config = app('config')->get('global-search');
        $mappings = $config['mappings'] ?? [];

        // Find the mapping for this model class
        foreach ($mappings as $mapping) {
            if ($mapping['model'] === $this->modelClass) {
                $baseIndex = $mapping['index'];
                break;
            }
        }

        // Fallback to class name if no mapping found
        if (!isset($baseIndex)) {
            $baseIndex = strtolower(class_basename($this->modelClass));
        }
        
        if ($tenant) {
            // Use TenantResolver to get properly normalized index name
            $tenantResolver = app(\LaravelGlobalSearch\GlobalSearch\Support\TenantResolver::class);
            return $tenantResolver->getTenantIndexName($baseIndex, $tenant);
        }
        
        return $baseIndex;
    }
    
    private function needsTenantInitialization(): bool
    {
        // Check if Stancl/Tenancy is available
        return class_exists(\Stancl\Tenancy\Tenancy::class);
    }
    
    private function getActualTenantId(?string $tenant): ?string
    {
        // If tenant is "default" or null, try to get the first available tenant
        if ($tenant === 'default' || $tenant === null) {
            try {
                // Try to get tenants from Stancl/Tenancy
                if (class_exists(\Stancl\Tenancy\Models\Tenant::class)) {
                    $firstTenant = \Stancl\Tenancy\Models\Tenant::first();
                    return $firstTenant ? $firstTenant->id : null;
                }
                
                // Try to get from tenancy() helper
                if (function_exists('tenancy')) {
                    $tenants = tenancy()->all();
                    if (!empty($tenants)) {
                        return array_keys($tenants)[0];
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to get tenant ID in IndexJob: {$e->getMessage()}");
            }
        }
        
        return $tenant;
    }
    
    private function initializeTenantContext(string $tenant): void
    {
        try {
            // Try to initialize tenant context using Stancl/Tenancy
            if (function_exists('tenancy')) {
                tenancy()->initialize($tenant);
            } elseif (class_exists(\Stancl\Tenancy\Tenancy::class)) {
                app(\Stancl\Tenancy\Tenancy::class)->initialize($tenant);
            }
        } catch (\Exception $e) {
            Log::error("Failed to initialize tenant context in IndexJob: {$e->getMessage()}");
            throw $e;
        }
    }
    
    private function ensureIndexExistsWithPrimaryKey($client, string $indexName): void
    {
        try {
            // Get the primary key from the mapping configuration
            $primaryKey = $this->getPrimaryKeyFromMapping();
            
            if ($primaryKey) {
                // Check if index exists and has the correct primary key
                try {
                    $index = $client->index($indexName);
                    $settings = $index->getSettings();
                    $currentPrimaryKey = $settings['primaryKey'] ?? null;
                    if ($currentPrimaryKey !== $primaryKey) {
                        // Index exists but has wrong/no primary key - recreate it
                        $this->recreateIndexWithPrimaryKey($client, $indexName, $primaryKey);
                    }
                } catch (\Exception $e) {
                    // Index doesn't exist - create it with primary key
                    $this->createIndexWithPrimaryKey($client, $indexName, $primaryKey);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to ensure index exists with primary key for {$indexName}: {$e->getMessage()}");
            // Don't throw - continue with indexing even if index creation fails
        }
    }
    
    private function createIndexWithPrimaryKey($client, string $indexName, string $primaryKey): void
    {
        try {
            $client->createIndex($indexName, ['primaryKey' => $primaryKey]);
            Log::info("Created index {$indexName} with primary key '{$primaryKey}'");
        } catch (\Exception $e) {
            Log::error("Failed to create index {$indexName}: {$e->getMessage()}");
        }
    }
    
    private function recreateIndexWithPrimaryKey($client, string $indexName, string $primaryKey): void
    {
        try {
            // Delete the existing index
            $client->deleteIndex($indexName);
            // Create new index with primary key
            $client->createIndex($indexName, ['primaryKey' => $primaryKey]);
            Log::info("Recreated index {$indexName} with primary key '{$primaryKey}'");
        } catch (\Exception $e) {
            Log::error("Failed to recreate index {$indexName}: {$e->getMessage()}");
        }
    }
    
    private function setPrimaryKeyViaRawAPI(string $indexName, string $primaryKey): void
    {
        try {
            $client = app(\Meilisearch\Client::class);
            $response = $client->patch("/indexes/{$indexName}", [
                'primaryKey' => $primaryKey
            ]);
            Log::info("Set primary key '{$primaryKey}' for index: {$indexName} via raw API");
        } catch (\Exception $e) {
            Log::error("Failed to set primary key via raw API for index {$indexName}: {$e->getMessage()}");
        }
    }
    
    private function getPrimaryKeyFromMapping(): ?string
    {
        $config = app('config')->get('global-search');
        $mappings = $config['mappings'] ?? [];

        // Find the mapping for this model class
        foreach ($mappings as $mapping) {
            if ($mapping['model'] === $this->modelClass) {
                // Support both snake_case and camelCase formats
                return $mapping['primary_key'] ?? $mapping['primaryKey'] ?? 'id';
            }
        }

        // Default to 'id' if no mapping found
        return 'id';
    }
}
