<?php

namespace LaravelGlobalSearch\GlobalSearch\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;
use LaravelGlobalSearch\GlobalSearch\Contracts\TenantResolver;

/**
 * Global search service that provides federated search across multiple indexes.
 */
class GlobalSearchService
{
    /**
     * Create a new global search service instance.
     */
    public function __construct(
        private SearchIndexManager $indexManager,
        private TenantResolver $tenantResolver,
        private CacheRepository $cache,
        private array $config
    ) {
    }

    /**
     * Perform a global search across all configured indexes.
     */
    public function search(string $query, array $filtersByIndex = [], int $limit = 10, ?string $tenantId = null): array
    {
        $federation = $this->config['federation'] ?? [];
        $indexes = array_keys($federation['indexes'] ?? []);
        
        if (empty($indexes)) {
            return $this->buildEmptySearchResult($query, $limit);
        }

        // Use provided tenant ID or resolve from current context
        $tenant = $tenantId ?? $this->tenantResolver->getCurrentTenant();
        
        // Get tenant-specific index names
        $tenantIndexes = $this->getTenantIndexNames($indexes, $tenant);

        // Check cache first
        $cacheKey = $this->buildCacheKey($query, $filtersByIndex, $tenantIndexes, $limit, $tenant);
        if ($this->isCacheEnabled() && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Perform search across all tenant indexes
        $searchResults = $this->performFederatedSearch($query, $filtersByIndex, $tenantIndexes, $limit, $tenant);
        
        // Cache the results
        if ($this->isCacheEnabled()) {
            $this->cacheResults($cacheKey, $searchResults);
        }

        return $searchResults;
    }

    /**
     * Build an empty search result.
     */
    private function buildEmptySearchResult(string $query, int $limit): array
    {
        return [
            'hits' => [],
            'meta' => [
                'total' => 0,
                'indexes' => [],
                'query' => $query,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * Get tenant-specific index names.
     */
    private function getTenantIndexNames(array $baseIndexes, ?string $tenantId): array
    {
        if (!$this->tenantResolver->isMultiTenant() || !$tenantId) {
            return $baseIndexes;
        }

        $tenantIndexes = [];
        foreach ($baseIndexes as $index) {
            $tenantIndexes[$index] = $this->tenantResolver->getTenantIndexName($index);
        }

        return $tenantIndexes;
    }

    /**
     * Build cache key for search results.
     */
    private function buildCacheKey(string $query, array $filtersByIndex, array $indexes, int $limit, ?string $tenantId = null): string
    {
        $versions = $this->getIndexVersions($indexes);
        $filtersHash = sha1(json_encode($filtersByIndex));
        $indexesHash = sha1(implode(',', $indexes));
        $tenantHash = $tenantId ? sha1($tenantId) : 'single';
        
        return "gs:{$tenantHash}:" . implode('|', $versions) . ":{$query}:{$filtersHash}:{$indexesHash}:{$limit}";
    }

    /**
     * Get version numbers for all indexes.
     */
    private function getIndexVersions(array $indexes): array
    {
        $versions = [];
        $cacheStore = $this->config['cache']['store'] ?? null;
        $versionKeyPrefix = $this->config['cache']['version_key_prefix'] ?? 'gs:index:';
        
        foreach ($indexes as $index) {
            $versions[$index] = (int) Cache::store($cacheStore)->get($versionKeyPrefix . $index, 0);
        }
        
        return $versions;
    }

    /**
     * Check if caching is enabled.
     */
    private function isCacheEnabled(): bool
    {
        return (bool) ($this->config['cache']['enabled'] ?? false);
    }

    /**
     * Perform the actual federated search.
     */
    private function performFederatedSearch(string $query, array $filtersByIndex, array $indexes, int $limit, ?string $tenantId = null): array
    {
        $meilisearchClient = App::make(\LaravelGlobalSearch\GlobalSearch\Support\MeilisearchClient::class);
        $federation = $this->config['federation'] ?? [];
        $allHits = [];
        $totalHits = 0;

        foreach ($indexes as $baseIndexName => $tenantIndexName) {
            try {
                $searchOptions = $this->buildSearchOptions($filtersByIndex, $baseIndexName, $limit);
                
                // Add tenant filter if multi-tenant
                if ($this->tenantResolver->isMultiTenant() && $tenantId) {
                    $searchOptions['filter'] = $this->addTenantFilter($searchOptions['filter'] ?? '', $tenantId);
                }
                
                $searchResult = $meilisearchClient->search($tenantIndexName, $query, $searchOptions);
                
                $weightedHits = $this->applyIndexWeighting(
                    $searchResult['hits'] ?? [],
                    $baseIndexName,
                    $federation['indexes'][$baseIndexName] ?? []
                );
                
                $allHits = array_merge($allHits, $weightedHits);
                $totalHits += (int) ($searchResult['estimatedTotalHits'] ?? 0);
                
            } catch (\Exception $e) {
                Log::error("Search failed for index: {$tenantIndexName}", [
                    'query' => $query,
                    'tenant' => $tenantId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->buildSearchResult($allHits, $totalHits, array_keys($indexes), $query, $limit);
    }

    /**
     * Add tenant filter to existing filter string.
     */
    private function addTenantFilter(string $existingFilter, string $tenantId): string
    {
        $tenantFilter = "_tenant_id = {$tenantId}";
        
        if (empty($existingFilter)) {
            return $tenantFilter;
        }
        
        return "({$existingFilter}) AND ({$tenantFilter})";
    }

    /**
     * Build search options for a specific index.
     */
    private function buildSearchOptions(array $filtersByIndex, string $indexName, int $limit): array
    {
        $options = ['limit' => $limit];
        
        if (!empty($filtersByIndex[$indexName])) {
            $options['filter'] = $filtersByIndex[$indexName];
        }
        
        return $options;
    }

    /**
     * Apply index weighting to search hits.
     */
    private function applyIndexWeighting(array $hits, string $indexName, array $indexConfig): array
    {
        $weight = (float) ($indexConfig['weight'] ?? 1.0);
        
        foreach ($hits as &$hit) {
            $hit['_index'] = $indexName;
            $hit['_score'] = $this->calculateHitScore($hit, $weight);
        }
        
        return $hits;
    }

    /**
     * Calculate score for a search hit.
     */
    private function calculateHitScore(array $hit, float $weight): float
    {
        $baseScore = !empty($hit['_matchesPosition']) ? 1.0 : 0.5;
        return $baseScore * max(0.1, $weight);
    }

    /**
     * Build the final search result.
     */
    private function buildSearchResult(array $allHits, int $totalHits, array $indexes, string $query, int $limit): array
    {
        // Sort hits by score and timestamp
        usort($allHits, function ($a, $b) {
            $scoreComparison = ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0);
            
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }
            
            $timestampA = strtotime($a['updated_at'] ?? '0');
            $timestampB = strtotime($b['updated_at'] ?? '0');
            
            return $timestampB <=> $timestampA;
        });

        return [
            'hits' => array_slice($allHits, 0, $limit),
            'meta' => [
                'total' => $totalHits,
                'indexes' => $indexes,
                'query' => $query,
                'limit' => $limit,
            ],
        ];
    }

    /**
     * Cache search results.
     */
    private function cacheResults(string $cacheKey, array $results): void
    {
        $ttl = (int) ($this->config['cache']['ttl'] ?? 60);
        
        try {
            $this->cache->put($cacheKey, $results, $ttl);
        } catch (\Exception $e) {
            Log::warning("Failed to cache search results", [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
        }
    }
}
