<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use LaravelGlobalSearch\GlobalSearch\Services\PerformanceMonitor;

class PerformanceCommand extends Command
{
    protected $signature = 'search:performance';
    protected $description = 'Show global search performance metrics and optimization recommendations';

    public function handle(PerformanceMonitor $monitor): int
    {
        $this->info('🔍 Global Search Performance Metrics');
        $this->newLine();

        $stats = $monitor->getPerformanceStats();

        // Display metrics
        $this->table([
            'Metric', 'Value', 'Status'
        ], [
            [
                'Average Search Time',
                number_format($stats['avg_search_time'], 3) . 's',
                $this->getStatusIcon($stats['avg_search_time'], [0.1, 0.5, 1.0])
            ],
            [
                'Total Searches',
                number_format($stats['total_searches']),
                '📊'
            ],
            [
                'Cache Hit Rate',
                number_format($stats['cache_hit_rate'], 1) . '%',
                $this->getStatusIcon($stats['cache_hit_rate'], [80, 90, 95])
            ],
            [
                'Avg Memory Usage',
                $this->formatBytes($stats['memory_usage']['avg']),
                $this->getStatusIcon($stats['memory_usage']['avg'], [1024*1024, 5*1024*1024, 10*1024*1024])
            ],
            [
                'Peak Memory Usage',
                $this->formatBytes($stats['memory_usage']['max']),
                $this->getStatusIcon($stats['memory_usage']['max'], [5*1024*1024, 20*1024*1024, 50*1024*1024])
            ],
        ]);

        $this->newLine();
        $this->displayRecommendations($stats);

        return 0;
    }

    private function getStatusIcon(float $value, array $thresholds): string
    {
        if ($value <= $thresholds[0]) return '🟢';
        if ($value <= $thresholds[1]) return '🟡';
        if ($value <= $thresholds[2]) return '🟠';
        return '🔴';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return number_format($bytes, 1) . ' ' . $units[$unitIndex];
    }

    private function displayRecommendations(array $stats): void
    {
        $this->info('💡 Performance Recommendations:');
        $this->newLine();

        $recommendations = [];

        if ($stats['avg_search_time'] > 0.5) {
            $recommendations[] = '• Consider increasing search result cache duration';
        }

        if ($stats['cache_hit_rate'] < 80) {
            $recommendations[] = '• Cache hit rate is low - check cache configuration';
        }

        if ($stats['memory_usage']['avg'] > 10 * 1024 * 1024) {
            $recommendations[] = '• High memory usage - consider reducing batch sizes in IndexJob';
        }

        if ($stats['total_searches'] > 1000) {
            $recommendations[] = '• High search volume - consider using Redis for caching';
        }

        if (empty($recommendations)) {
            $this->info('✅ Performance looks good! No immediate optimizations needed.');
        } else {
            foreach ($recommendations as $recommendation) {
                $this->line($recommendation);
            }
        }
    }
}
