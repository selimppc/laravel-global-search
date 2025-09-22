<?php

namespace LaravelGlobalSearch\GlobalSearch\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Contracts\SearchDocumentTransformer;

/**
 * Manages search indexes and document operations.
 */
class SearchIndexManager
{
    /**
     * Create a new search index manager instance.
     */
    public function __construct(
        private MeilisearchClient $meilisearchClient,
        private array $config
    ) {
    }

    /**
     * Get all configured mappings.
     */
    public function getMappings(): array
    {
        return $this->config['mappings'] ?? [];
    }

    /**
     * Find mapping configuration for a specific model class.
     */
    public function findMappingByModel(string $modelClass): ?array
    {
        foreach ($this->getMappings() as $mapping) {
            if (($mapping['model'] ?? null) === $modelClass) {
                return $mapping;
            }
        }
        
        return null;
    }
    /**
     * Build a search document from an Eloquent model.
     */
    public function buildDocument(Model $model, array $mapping): array
    {
        $document = [];
        
        // Add mapped fields
        foreach (($mapping['fields'] ?? []) as $field) {
            $document[$field] = data_get($model, $field);
        }
        
        // Add computed fields
        foreach (($mapping['computed'] ?? []) as $key => $callback) {
            try {
                $document[$key] = $callback($model);
            } catch (\Exception $e) {
                Log::warning("Failed to compute field '{$key}' for model " . get_class($model), [
                    'model_id' => $model->getKey(),
                    'error' => $e->getMessage()
                ]);
                $document[$key] = null;
            }
        }
        
        // Set primary key
        $primaryKey = $mapping['primary_key'] ?? $model->getKeyName();
        $document[$primaryKey] = $model->getKey();
        
        return $document;
    }

    /**
     * Get the document transformer for a mapping.
     */
    public function getDocumentTransformer(array $mapping): ?SearchDocumentTransformer
    {
        $transformerClass = $mapping['transformer'] ?? null;
        
        if (!$transformerClass) {
            return null;
        }
        
        try {
            return app($transformerClass);
        } catch (\Exception $e) {
            Log::error("Failed to resolve transformer '{$transformerClass}'", [
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Index multiple models to their search index.
     */
    public function indexModels(string $modelClass, array $modelIds): void
    {
        $mapping = $this->findMappingByModel($modelClass);
        if (!$mapping) {
            Log::warning("No mapping found for model: {$modelClass}");
            return;
        }

        $model = new $modelClass;
        $primaryKey = $mapping['primary_key'] ?? $model->getKeyName();
        $batchSize = (int) ($this->config['pipeline']['batch_size'] ?? 1000);

        $query = $modelClass::query()->whereIn($primaryKey, $modelIds);
        $batch = [];
        $transformer = $this->getDocumentTransformer($mapping);

        try {
            foreach ($query->cursor() as $modelInstance) {
                $document = $transformer 
                    ? $transformer($modelInstance, $mapping)
                    : $this->buildDocument($modelInstance, $mapping);
                
                $batch[] = $document;

                if (count($batch) >= $batchSize) {
                    $this->meilisearchClient->addDocuments(
                        $mapping['index'], 
                        $batch, 
                        $mapping['primary_key'] ?? null
                    );
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $this->meilisearchClient->addDocuments(
                    $mapping['index'], 
                    $batch, 
                    $mapping['primary_key'] ?? null
                );
            }

            $this->incrementIndexVersion($mapping['index']);
            
        } catch (\Exception $e) {
            Log::error("Failed to index models for {$modelClass}", [
                'model_ids' => $modelIds,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Indexing failed: {$e->getMessage()}", 0, $e);
        }
    }



    /**
     * Delete models from their search index.
     */
    public function deleteModels(string $modelClass, array $modelIds): void
    {
        $mapping = $this->findMappingByModel($modelClass);
        if (!$mapping) {
            Log::warning("No mapping found for model: {$modelClass}");
            return;
        }

        try {
            $this->meilisearchClient->deleteDocuments($mapping['index'], $modelIds);
            $this->incrementIndexVersion($mapping['index']);
        } catch (\Exception $e) {
            Log::error("Failed to delete models for {$modelClass}", [
                'model_ids' => $modelIds,
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Delete models failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Flush all documents from an index.
     */
    public function flushIndex(string $indexName): void
    {
        try {
            $this->meilisearchClient->deleteAllDocuments($indexName);
            $this->incrementIndexVersion($indexName);
        } catch (\Exception $e) {
            Log::error("Failed to flush index: {$indexName}", [
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException("Flush index failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Sync index settings from configuration.
     */
    public function syncIndexSettings(): void
    {
        $indexSettings = $this->config['index_settings'] ?? [];
        
        if (empty($indexSettings)) {
            Log::info('No index settings configured');
            return;
        }

        foreach ($indexSettings as $indexName => $settings) {
            try {
                $this->meilisearchClient->updateSettings($indexName, $settings);
                Log::info("Synced settings for index: {$indexName}");
            } catch (\Exception $e) {
                Log::error("Failed to sync settings for index: {$indexName}", [
                    'error' => $e->getMessage()
                ]);
                
                throw new \RuntimeException("Sync settings failed for {$indexName}: {$e->getMessage()}", 0, $e);
            }
        }
    }

    /**
     * Increment the version number for an index to invalidate cache.
     */
    public function incrementIndexVersion(string $indexName): void
    {
        $cacheStore = $this->config['cache']['store'] ?? null;
        $versionKeyPrefix = $this->config['cache']['version_key_prefix'] ?? 'gs:index:';
        
        try {
            cache()->store($cacheStore)->increment($versionKeyPrefix . $indexName);
        } catch (\Exception $e) {
            Log::warning("Failed to increment version for index: {$indexName}", [
                'error' => $e->getMessage()
            ]);
        }
    }

}