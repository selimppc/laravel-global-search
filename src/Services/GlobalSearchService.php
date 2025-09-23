<?php

namespace LaravelGlobalSearch\GlobalSearch\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use LaravelGlobalSearch\GlobalSearch\Jobs\IndexJob;
use LaravelGlobalSearch\GlobalSearch\Jobs\DeleteJob;
use Meilisearch\Client;

/**
 * Modern, minimal search service with job-based operations for scale.
 */
class GlobalSearchService
{
    public function __construct(
        private TenantResolver $tenantResolver,
        private array $config
    ) {}

    public function search(string $query, array $filters = [], int $limit = 10, ?string $tenant = null): array
    {
        try {
            $tenant = $tenant ?? $this->tenantResolver->getCurrentTenant();
            $indexes = $this->getIndexes();
            
            if (empty($indexes)) {
                return $this->emptyResult($query, $limit);
            }

            $results = $this->performSearch($query, $filters, $indexes, $limit, $tenant);
            
            // Log only errors, minimal info logging
            if (empty($results['hits'])) {
                Log::error('Search returned no results', compact('query', 'tenant', 'filters'));
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Search failed', [
                'query' => $query,
                'tenant' => $tenant,
                'error' => $e->getMessage()
            ]);
            
            return $this->emptyResult($query, $limit);
        }
    }

    public function indexModel($model, ?string $tenant = null): void
    {
        $tenant = $tenant ?? $this->tenantResolver->getCurrentTenant();
        
        // Always use jobs for scale
        IndexJob::dispatch($model, $tenant)
            ->onQueue($this->config['pipeline']['queue'] ?? 'default');
    }

    public function deleteModel($model, ?string $tenant = null): void
    {
        $tenant = $tenant ?? $this->tenantResolver->getCurrentTenant();
        
        // Always use jobs for scale
        DeleteJob::dispatch($model, $tenant)
            ->onQueue($this->config['pipeline']['queue'] ?? 'default');
    }

    public function reindexAll(?string $tenant = null): void
    {
        $tenant = $tenant ?? $this->tenantResolver->getCurrentTenant();
        $mappings = $this->config['mappings'] ?? [];

        if (empty($mappings)) {
            Log::warning('No model mappings found in configuration. Nothing to reindex.');
            return;
        }

        $totalJobs = 0;
        foreach ($mappings as $mapping) {
            $modelClass = $mapping['model'];
            if (class_exists($modelClass)) {
                try {
                    // Get all model IDs and dispatch them in batches
                    $batchSize = $this->config['pipeline']['batch_size'] ?? 1000;
                    $modelCount = 0;
                    
                    $modelClass::query()->chunkById($batchSize, function ($chunk) use ($modelClass, $tenant, &$modelCount, &$totalJobs) {
                        $modelIds = $chunk->pluck('id')->toArray();
                        if (!empty($modelIds)) {
                            IndexJob::dispatch($modelClass, $modelIds, $tenant)
                                ->onQueue($this->config['pipeline']['queue'] ?? 'default');
                            $modelCount += count($modelIds);
                            $totalJobs++;
                        }
                    });
                    
                    Log::info("Dispatched reindex jobs for {$modelClass}: {$modelCount} models in {$totalJobs} batches");
                } catch (\Exception $e) {
                    Log::error("Failed to reindex {$modelClass}: {$e->getMessage()}", ['exception' => $e]);
                }
            } else {
                Log::warning("Model class {$modelClass} not found, skipping reindex");
            }
        }
        
        Log::info("Reindex completed: {$totalJobs} jobs dispatched for tenant: " . ($tenant ?? 'default'));
    }

    private function performSearch(string $query, array $filters, array $indexes, int $limit, ?string $tenant): array
    {
        $client = App::make(Client::class);
        $allHits = [];
        $totalHits = 0;

        foreach ($indexes as $indexName) {
            try {
                $tenantIndex = $this->tenantResolver->getTenantIndexName($indexName, $tenant);
                $searchOptions = $this->buildSearchOptions($filters, $limit, $tenant);
                
                // Get the index first, then call search on it
                $index = $client->index($tenantIndex);
                $result = $index->search($query, $searchOptions);
                
                $hits = $result->getHits();
                foreach ($hits as &$hit) {
                    $hit['_index'] = $indexName;
                }
                
                $allHits = array_merge($allHits, $hits);
                $totalHits += $result->getEstimatedTotalHits();
                
            } catch (\Exception $e) {
                Log::error("Search failed for index: {$indexName}", [
                    'query' => $query,
                    'tenant' => $tenant,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Sort by score and limit
        usort($allHits, fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
        $limitedHits = array_slice($allHits, 0, $limit);

        return [
            'hits' => $limitedHits,
            'meta' => [
                'total' => $totalHits,
                'indexes' => array_keys($indexes),
                'query' => $query,
                'limit' => $limit,
                'tenant' => $tenant
            ]
        ];
    }

    private function getIndexes(): array
    {
        return array_keys($this->config['federation']['indexes'] ?? []);
    }

    private function buildSearchOptions(array $filters, int $limit, ?string $tenant): array
    {
        $options = ['limit' => $limit];
        
        if (!empty($filters)) {
            $options['filter'] = $this->buildFilterString($filters);
        }
        
        if ($tenant && $this->tenantResolver->isMultiTenant()) {
            $existingFilter = $options['filter'] ?? '';
            $tenantFilter = "tenant_id = {$tenant}";
            $options['filter'] = $existingFilter ? "({$existingFilter}) AND ({$tenantFilter})" : $tenantFilter;
        }
        
        return $options;
    }

    private function buildFilterString(array $filters): string
    {
        $conditions = [];
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $conditions[] = "{$field} IN (" . implode(', ', $value) . ")";
            } else {
                $conditions[] = "{$field} = {$value}";
            }
        }
        return implode(' AND ', $conditions);
    }

    private function emptyResult(string $query, int $limit): array
    {
        return [
            'hits' => [],
            'meta' => [
                'total' => 0,
                'indexes' => [],
                'query' => $query,
                'limit' => $limit
            ]
        ];
    }

    public function flushAll(?string $tenant = null): void
    {
        $tenant = $tenant ?? $this->tenantResolver->getCurrentTenant();
        $indexes = array_keys($this->config['federation']['indexes'] ?? []);

        try {
            $client = App::make(Client::class);
            foreach ($indexes as $indexName) {
                $tenantIndexName = $this->tenantResolver->getTenantIndexName($indexName, $tenant);
                $client->index($tenantIndexName)->deleteAllDocuments();
                Log::info("Flushed index: {$tenantIndexName}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to flush indexes: {$e->getMessage()}", ['exception' => $e]);
        }
    }

    public function syncSettings(?string $tenant = null): void
    {
        $tenant = $tenant ?? $this->tenantResolver->getCurrentTenant();
        $indexes = array_keys($this->config['federation']['indexes'] ?? []);

        try {
            $client = App::make(Client::class);
            foreach ($indexes as $indexName) {
                $tenantIndexName = $this->tenantResolver->getTenantIndexName($indexName, $tenant);
                
                // Get settings for this specific index from mappings
                $settings = $this->getIndexSettings($indexName);
                if (!empty($settings)) {
                    $client->index($tenantIndexName)->updateSettings($settings);
                    Log::info("Synced settings for index: {$tenantIndexName}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync settings: {$e->getMessage()}", ['exception' => $e]);
        }
    }

    private function getIndexSettings(string $indexName): array
    {
        $mappings = $this->config['mappings'] ?? [];
        foreach ($mappings as $mapping) {
            if (strtolower(class_basename($mapping['model'])) === $indexName) {
                return $mapping['index_settings'] ?? [];
            }
        }
        return [];
    }
}
