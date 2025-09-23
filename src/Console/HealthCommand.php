<?php

namespace LaravelGlobalSearch\GlobalSearch\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use LaravelGlobalSearch\GlobalSearch\Support\TenantResolver;
use Meilisearch\Client;

class HealthCommand extends Command
{
    protected $signature = 'search:health {--detailed} {--tenant=}';
    protected $description = 'Check search system health';

    public function handle(TenantResolver $tenantResolver): int
    {
        $detailed = $this->option('detailed');
        $tenant = $this->option('tenant');
        
        $this->info('🔍 Checking Global Search System Health...');
        $this->newLine();
        
        $overallHealth = true;
        
        // Check Meilisearch connection
        $overallHealth &= $this->checkMeilisearchConnection($detailed);
        
        // Check tenant-specific health if tenant specified
        if ($tenant) {
            $overallHealth &= $this->checkTenantHealth($tenant, $tenantResolver, $detailed);
        } else {
            // Check all tenants
            $tenants = $tenantResolver->getAllTenants();
            if (!empty($tenants)) {
                foreach ($tenants as $tenantId) {
                    $overallHealth &= $this->checkTenantHealth($tenantId, $tenantResolver, $detailed);
                }
            } else {
                $this->info('ℹ️  No tenants configured - checking default index only');
            }
        }
        
        $this->newLine();
        if ($overallHealth) {
            $this->info('<info>✅ All systems healthy!</info>');
            return Command::SUCCESS;
        } else {
            $this->error('<error>❌ Some issues detected. Check the output above.</error>');
            return Command::FAILURE;
        }
    }
    
    private function checkMeilisearchConnection(bool $detailed): bool
    {
        try {
            $client = App::make(Client::class);
            $health = $client->health();
            
            // The health() method returns an array with status information
            if (is_array($health) && isset($health['status']) && $health['status'] === 'available') {
                $this->info('<info>✅</info> Meilisearch connection: Healthy');
                if ($detailed) {
                    $this->line("   Status: {$health['status']}");
                    if (isset($health['version'])) {
                        $this->line("   Version: {$health['version']}");
                    }
                }
                return true;
            } else {
                $this->error('<error>❌</error> Meilisearch connection: Unhealthy');
                if ($detailed) {
                    $this->line("   Status: " . (is_array($health) ? ($health['status'] ?? 'unknown') : 'unknown'));
                }
                return false;
            }
        } catch (\Exception $e) {
            $this->error('<error>❌</error> Meilisearch connection: Failed');
            if ($detailed) {
                $this->line("   Error: {$e->getMessage()}");
            }
            return false;
        }
    }
    
    private function checkTenantHealth(string $tenant, TenantResolver $tenantResolver, bool $detailed): bool
    {
        try {
            $client = App::make(Client::class);
            $config = App::make('config')->get('global-search');
            $indexes = array_keys($config['federation']['indexes'] ?? []);
            
            $allHealthy = true;
            foreach ($indexes as $indexName) {
                $tenantIndexName = $tenantResolver->getTenantIndexName($indexName);
                
                try {
                    $index = $client->index($tenantIndexName);
                    // Just check if we can access the index - this will throw if it doesn't exist
                    $index->getSettings();
                    
                    if ($detailed) {
                        $this->line("   Index: {$tenantIndexName} (exists)");
                    }
                } catch (\Exception $e) {
                    $this->error("   Index {$tenantIndexName}: {$e->getMessage()}");
                    $allHealthy = false;
                }
            }
            
            if ($allHealthy) {
                $this->info("<info>✅</info> Tenant '{$tenant}': Healthy");
            } else {
                $this->error("<error>❌</error> Tenant '{$tenant}': Some indexes failed");
            }
            
            return $allHealthy;
        } catch (\Exception $e) {
            $this->error("<error>❌</error> Tenant '{$tenant}': Failed");
            $this->error("   Error: {$e->getMessage()}");
            return false;
        }
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
