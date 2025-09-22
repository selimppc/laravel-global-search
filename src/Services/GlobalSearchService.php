<?php

namespace LaravelGlobalSearch\GlobalSearch\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use LaravelGlobalSearch\GlobalSearch\Support\SearchIndexManager;

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
        private CacheRepository $cache,
        private array $config
    ) {
    }

    /**
     * Perform a global search across all configured indexes.
     */
    public function search(string $query, array $filtersByIndex = [], int $limit = 10): array
    {
        $federation = $this->config['federation'] ?? [];
        $indexes = array_keys($federation['indexes'] ?? []);
        
        if (empty($indexes)) {
            return $this->buildEmptySearchResult($query, $limit);
        }

        // Check cache first
        $cacheKey = $this->buildCacheKey($query, $filtersByIndex, $indexes, $limit);
        if ($this->isCacheEnabled() && $this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Perform search across all indexes
        $searchResults = $this->performFederatedSearch($query, $filtersByIndex, $indexes, $limit);
        
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
     * Build cache key for search results.
     */
    private function buildCacheKey(string $query, array $filtersByIndex, array $indexes, int $limit): string
    {
        $versions = $this->getIndexVersions($indexes);
        $filtersHash = sha1(json_encode($filtersByIndex));
        $indexesHash = sha1(implode(',', $indexes));
        
        return "gs:" . implode('|', $versions) . ":{$query}:{$filtersHash}:{$indexesHash}:{$limit}";
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
            $versions[$index] = (int) cache()->store($cacheStore)->get($versionKeyPrefix . $index, 0);
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
    private function performFederatedSearch(string $query, array $filtersByIndex, array $indexes, int $limit): array
    {
        $meilisearchClient = app(\LaravelGlobalSearch\GlobalSearch\Support\MeilisearchClient::class);
        $federation = $this->config['federation'] ?? [];
        $allHits = [];
        $totalHits = 0;

        foreach ($indexes as $indexName) {
            try {
                $searchOptions = $this->buildSearchOptions($filtersByIndex, $indexName, $limit);
                $searchResult = $meilisearchClient->search($indexName, $query, $searchOptions);
                
                $weightedHits = $this->applyIndexWeighting(
                    $searchResult['hits'] ?? [],
                    $indexName,
                    $federation['indexes'][$indexName] ?? []
                );
                
                $allHits = array_merge($allHits, $weightedHits);
                $totalHits += (int) ($searchResult['estimatedTotalHits'] ?? 0);
                
            } catch (\Exception $e) {
                Log::error("Search failed for index: {$indexName}", [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->buildSearchResult($allHits, $totalHits, $indexes, $query, $limit);
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
