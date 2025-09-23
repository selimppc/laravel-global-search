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

    public function handle(TenantResolver $tenantResolver): void
    {
        try {
            $tenant = $this->tenant ?? $tenantResolver->getCurrentTenant();
            
            // Tenant context should already be initialized globally
            // No need to manually initialize it here
            
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

                // Transform models to documents
                $chunkDocuments = $models->map(fn($model) => $this->transformModel($model, $tenant))->toArray();
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
            
            // Index documents
            $index = $client->index($indexName);
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

    private function transformModel($model, ?string $tenant): array
    {
        $data = $model->toArray();
        
        // Add tenant context automatically
        if ($tenant) {
            $data['tenant_id'] = $tenant;
        }
        
        // Add computed fields
        $data['url'] = $this->generateUrl($model);
        $data['type'] = class_basename($model);
        
        return $data;
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
            // Use tenant ID directly for proper multi-tenant isolation
            return "{$baseIndex}_{$tenant}";
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
}
