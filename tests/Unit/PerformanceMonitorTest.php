<?php

namespace LaravelGlobalSearch\GlobalSearch\Tests\Unit;

use LaravelGlobalSearch\GlobalSearch\Tests\TestCase;
use LaravelGlobalSearch\GlobalSearch\Services\PerformanceMonitor;
use Illuminate\Support\Facades\Cache;

class PerformanceMonitorTest extends TestCase
{
    public function test_performance_monitor_tracks_operations()
    {
        $monitor = new PerformanceMonitor();

        $monitor->startTimer('test_operation');
        usleep(1000); // 1ms
        $metrics = $monitor->endTimer('test_operation');

        $this->assertArrayHasKey('duration', $metrics);
        $this->assertArrayHasKey('memory_used', $metrics);
        $this->assertArrayHasKey('peak_memory', $metrics);
        $this->assertGreaterThan(0, $metrics['duration']);
    }

    public function test_performance_monitor_records_cache_hits()
    {
        $monitor = new PerformanceMonitor();

        $monitor->recordCacheHit();
        $monitor->recordCacheHit();
        $monitor->recordCacheMiss();

        $stats = $monitor->getPerformanceStats();
        
        $this->assertArrayHasKey('cache_hit_rate', $stats);
        $this->assertEquals(66.67, round($stats['cache_hit_rate'], 2));
    }

    public function test_performance_monitor_returns_stats()
    {
        $monitor = new PerformanceMonitor();

        $stats = $monitor->getPerformanceStats();

        $this->assertArrayHasKey('avg_search_time', $stats);
        $this->assertArrayHasKey('total_searches', $stats);
        $this->assertArrayHasKey('cache_hit_rate', $stats);
        $this->assertArrayHasKey('memory_usage', $stats);
    }

    public function test_performance_monitor_handles_missing_data()
    {
        Cache::flush();
        
        $monitor = new PerformanceMonitor();
        $stats = $monitor->getPerformanceStats();

        $this->assertEquals(0.0, $stats['avg_search_time']);
        $this->assertEquals(0, $stats['total_searches']);
        $this->assertEquals(0.0, $stats['cache_hit_rate']);
    }
}
