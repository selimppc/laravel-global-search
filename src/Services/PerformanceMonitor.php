<?php

namespace LaravelGlobalSearch\GlobalSearch\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Performance monitoring service for the global search package.
 * Tracks search performance metrics and provides optimization insights.
 */
class PerformanceMonitor
{
    private array $metrics = [];

    public function startTimer(string $operation): void
    {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(true)
        ];
    }

    public function endTimer(string $operation): array
    {
        if (!isset($this->metrics[$operation])) {
            return [];
        }

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metrics = [
            'duration' => $endTime - $this->metrics[$operation]['start_time'],
            'memory_used' => $endMemory - $this->metrics[$operation]['memory_start'],
            'peak_memory' => memory_get_peak_usage(true)
        ];

        // Log slow operations
        if ($metrics['duration'] > 1.0) { // More than 1 second
            Log::warning("Slow search operation: {$operation}", $metrics);
        }

        // Store metrics for analytics
        $this->storeMetrics($operation, $metrics);

        unset($this->metrics[$operation]);
        return $metrics;
    }

    public function getPerformanceStats(): array
    {
        $cacheKey = 'global_search.performance_stats';
        
        return Cache::remember($cacheKey, 300, function () {
            return [
                'avg_search_time' => $this->getAverageSearchTime(),
                'total_searches' => $this->getTotalSearches(),
                'cache_hit_rate' => $this->getCacheHitRate(),
                'memory_usage' => $this->getMemoryUsageStats(),
            ];
        });
    }

    private function storeMetrics(string $operation, array $metrics): void
    {
        $cacheKey = "global_search.metrics.{$operation}";
        $existing = Cache::get($cacheKey, []);
        
        $existing[] = [
            'timestamp' => now()->toISOString(),
            'duration' => $metrics['duration'],
            'memory_used' => $metrics['memory_used'],
        ];
        
        // Keep only last N entries (configurable)
        $maxEntries = config('global-search.performance.max_metrics_entries', 100);
        if (count($existing) > $maxEntries) {
            $existing = array_slice($existing, -$maxEntries);
        }
        
        // Cache TTL from config
        $cacheTtl = config('global-search.cache.ttl', 3600);
        Cache::put($cacheKey, $existing, $cacheTtl);
    }

    private function getAverageSearchTime(): float
    {
        $searchMetrics = Cache::get('global_search.metrics.search', []);
        
        if (empty($searchMetrics)) {
            return 0.0;
        }
        
        $totalTime = array_sum(array_column($searchMetrics, 'duration'));
        return $totalTime / count($searchMetrics);
    }

    private function getTotalSearches(): int
    {
        $searchMetrics = Cache::get('global_search.metrics.search', []);
        return count($searchMetrics);
    }

    private function getCacheHitRate(): float
    {
        $cacheHits = Cache::get('global_search.cache_hits', 0);
        $cacheMisses = Cache::get('global_search.cache_misses', 0);
        
        $total = $cacheHits + $cacheMisses;
        return $total > 0 ? ($cacheHits / $total) * 100 : 0.0;
    }

    private function getMemoryUsageStats(): array
    {
        $searchMetrics = Cache::get('global_search.metrics.search', []);
        
        if (empty($searchMetrics)) {
            return ['avg' => 0, 'max' => 0];
        }
        
        $memoryUsage = array_column($searchMetrics, 'memory_used');
        
        return [
            'avg' => array_sum($memoryUsage) / count($memoryUsage),
            'max' => max($memoryUsage),
        ];
    }

    public function recordCacheHit(): void
    {
        Cache::increment('global_search.cache_hits');
    }

    public function recordCacheMiss(): void
    {
        Cache::increment('global_search.cache_misses');
    }
}
