<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Bus;
use LaravelGlobalSearch\GlobalSearch\Support\MeilisearchClient;

/**
 * Command to diagnose and validate the global search configuration.
 */
class SearchDoctorCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'search:doctor 
                            {--fix : Attempt to fix common issues}
                            {--verbose : Show detailed information}';

    /**
     * The console command description.
     */
    protected $description = 'Diagnose and validate the global search configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Running global search diagnostics...');
        $this->newLine();

        $issues = [];
        $warnings = [];

        // Check configuration
        $this->checkConfiguration($issues, $warnings);
        
        // Check Meilisearch connection
        $this->checkMeilisearchConnection($issues, $warnings);
        
        // Check cache configuration
        $this->checkCacheConfiguration($issues, $warnings);
        
        // Check queue configuration
        $this->checkQueueConfiguration($issues, $warnings);

        // Display results
        $this->displayResults($issues, $warnings);

        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Check configuration validity.
     */
    private function checkConfiguration(array &$issues, array &$warnings): void
    {
        $this->info('📋 Checking configuration...');
        
        $config = Config::get('global-search');
        
        if (empty($config)) {
            $issues[] = 'Global search configuration not found. Run: php artisan vendor:publish --tag=global-search-config';
            return;
        }

        // Check required client configuration
        if (empty($config['client']['host'])) {
            $issues[] = 'Missing Meilisearch host configuration (client.host)';
        }

        // Check mappings
        if (empty($config['mappings'])) {
            $warnings[] = 'No model mappings configured. Add mappings to config/global-search.php';
        } else {
            $this->info("   ✅ Found " . count($config['mappings']) . " model mappings");
        }

        // Check federation configuration
        if (empty($config['federation']['indexes'])) {
            $warnings[] = 'No federation indexes configured. Add indexes to federation.indexes';
        } else {
            $this->info("   ✅ Found " . count($config['federation']['indexes']) . " federation indexes");
        }
    }

    /**
     * Check Meilisearch connection.
     */
    private function checkMeilisearchConnection(array &$issues, array &$warnings): void
    {
        $this->info('🔗 Checking Meilisearch connection...');
        
        $config = config('global-search.client');
        $host = $config['host'] ?? null;
        
        if (!$host) {
            $issues[] = 'Meilisearch host not configured';
            return;
        }

        try {
            $response = Http::timeout(5)->get(rtrim($host, '/') . '/health');
            
            if ($response->successful()) {
                $this->info('   ✅ Meilisearch is reachable');
                
                if ($this->option('verbose')) {
                    $health = $response->json();
                    $this->info("   Status: " . ($health['status'] ?? 'unknown'));
                }
            } else {
                $issues[] = "Meilisearch returned status: {$response->status()}";
            }
        } catch (\Exception $e) {
            $issues[] = "Cannot connect to Meilisearch: {$e->getMessage()}";
        }
    }

    /**
     * Check cache configuration.
     */
    private function checkCacheConfiguration(array &$issues, array &$warnings): void
    {
        $this->info('💾 Checking cache configuration...');
        
        $config = config('global-search.cache');
        $store = $config['store'] ?? 'default';
        
        try {
            $testKey = 'global-search-test-' . time();
            Cache::store($store)->put($testKey, 'test', 60);
            $value = Cache::store($store)->get($testKey);
            Cache::store($store)->forget($testKey);
            
            if ($value === 'test') {
                $this->info("   ✅ Cache store '{$store}' is working");
            } else {
                $issues[] = "Cache store '{$store}' is not working properly";
            }
        } catch (\Exception $e) {
            $issues[] = "Cache store '{$store}' error: {$e->getMessage()}";
        }
    }

    /**
     * Check queue configuration.
     */
    private function checkQueueConfiguration(array &$issues, array &$warnings): void
    {
        $this->info('⚡ Checking queue configuration...');
        
        $config = config('global-search.pipeline');
        $queue = $config['queue'] ?? 'default';
        
        try {
            // Test if we can dispatch a job
            $testJob = new \LaravelGlobalSearch\GlobalSearch\Jobs\IndexModelsJob([]);
            Bus::dispatch($testJob)->onQueue($queue);
            
            $this->info("   ✅ Queue '{$queue}' is configured");
        } catch (\Exception $e) {
            $warnings[] = "Queue '{$queue}' may have issues: {$e->getMessage()}";
        }
    }

    /**
     * Display diagnostic results.
     */
    private function displayResults(array $issues, array $warnings): void
    {
        $this->newLine();
        
        if (empty($issues) && empty($warnings)) {
            $this->info('🎉 All checks passed! Your global search is properly configured.');
            return;
        }

        if (!empty($issues)) {
            $this->error('❌ Issues found:');
            foreach ($issues as $issue) {
                $this->error("   • {$issue}");
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->warn("   • {$warning}");
            }
            $this->newLine();
        }

        if (!empty($issues)) {
            $this->info('💡 Run with --fix to attempt automatic fixes where possible.');
        }
    }
}