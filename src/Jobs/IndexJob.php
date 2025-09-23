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
            
            // Initialize tenant context if needed (Stancl/Tenancy)
            if ($tenant && $this->needsTenantInitialization()) {
                $this->initializeTenantContext($tenant);
            }
            
            $client = App::make(Client::class);
            
            // Get model instances
            $models = $this->modelClass::whereIn('id', $this->modelIds)->get();
            
            if ($models->isEmpty()) {
                Log::error('No models found for indexing', [
                    'model' => $this->modelClass,
                    'ids' => $this->modelIds,
                    'tenant' => $tenant
                ]);
                return;
            }

            // Transform models to documents
            $documents = $models->map(fn($model) => $this->transformModel($model, $tenant))->toArray();
            
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
        
        if (route()->has($routeName)) {
            return route($routeName, $model->id);
        }
        
        return url('/') . '/' . strtolower(class_basename($model)) . '/' . $model->id;
    }

    private function getIndexName(?string $tenant): string
    {
        $baseIndex = strtolower(class_basename($this->modelClass));
        return $tenant ? "{$baseIndex}_{$tenant}" : $baseIndex;
    }
    
    private function needsTenantInitialization(): bool
    {
        // Check if Stancl/Tenancy is available
        return class_exists(\Stancl\Tenancy\Tenancy::class);
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
